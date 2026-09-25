<?php
class UploaderHandler
{

    public $allowedExtensions = array();
    public $sizeLimit = null;
    public $uploadDirectory = '';
    public $inputFileName = '';
    protected $uploadName;

    ## Get the original filename
    public function getName()
    {
        if (isset($_REQUEST['qqfilename'])) return $_REQUEST['qqfilename'];
        if (isset($_FILES[$this->inputFileName])) return $_FILES[$this->inputFileName]['name'];
    }

    /**
     * Get the name of the uploaded file
     */
    public function getUploadName()
    {
        return $this->uploadName;
    }

    public function handleFileUpload($name = null)
    {
        ## Set value of uploadedFile -> Uploaded File
        $uploadedFile = $_FILES[$this->inputFileName];

        ## check file error
        if ($uploadedFile['error']) {
            return array('error' => 'Upload Error #' . $uploadedFile['error']);
        }

        $directory = $this->uploadDirectory;

        ## Check if the destination directory exists
        if (!is_dir($directory)) {
            ## Create the destination directory if it doesn't exist
            if (mkdir($directory, 0777, true)) {
            } else {
                return array('error' => "Server error. Unable to create uploads directory");
            }
        }

        ## Check if the directory is inaccessible
        if ($this->isInaccessible($directory)) {
            return array('error' => "Server error. Uploads directory isn't writable");
        }

        $type = $_SERVER['CONTENT_TYPE'];
        if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            $type = $_SERVER['HTTP_CONTENT_TYPE'];
        }

        if (!isset($type)) {
            return array('error' => "No files were uploaded.");
        } else if (strpos(strtolower($type), 'multipart/') !== 0) {
            return array('error' => "Server error. Not a multipart request. Please set forceMultipart to default value (true).");
        }

        ## Get name and size 
        $nameFile = $uploadedFile['name'];
        $sizeFile = $uploadedFile['size'];

        if (isset($_REQUEST['qqtotalfilesize'])) {
            $sizeFile = $_REQUEST['qqtotalfilesize'];
        }

        if ($nameFile === null) {
            $nameFile = $this->getName();
        }

        ## Validate name
        if ($nameFile === null || $nameFile === '') {
            return array('error' => 'File name empty.');
        }

        ## Validate file size
        if ($sizeFile == 0) {
            return array('error' => 'File is empty.');
        }

        if (!is_null($this->sizeLimit) && $sizeFile > $this->sizeLimit) {
            return array('error' => 'File is too large.', 'preventRetry' => true);
        }

        ## Generate new file name
        $nameFile = $this->generateNewFileName($nameFile);

        $pathinfo = pathinfo($nameFile);
        $ext = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
        $file_name = $pathinfo['basename'];
        $tmp_name = $uploadedFile['tmp_name'];

        ## Validate file extension
        if ($this->allowedExtensions && !in_array(strtolower($ext), array_map("strtolower", $this->allowedExtensions))) {
            $these = implode(', ', $this->allowedExtensions);
            return array('error' => 'File has an invalid extension, it should be one of ' . $these . '.');
        }

        ## Move the uploaded file to the desired location
        $target = $this->uploadDirectory . $file_name;
        if (move_uploaded_file($tmp_name, $target)) {
            $this->uploadName = $file_name;
            return array('success' => true);
        }

        return array('error' => 'Could not save uploaded file. The upload was cancelled, or server error encountered');
    }

    public function getTargetFilePath()
    {
        $destination = rtrim($this->uploadDirectory, '/') . '/'; ## Ensure the destination has a trailing slash

        $targetFilePath = $destination . $this->getUploadName();
        return $targetFilePath;
    }

    private function generateNewFileName($originalFileName)
    {
        // $file_name = pathinfo($originalFileName, PATHINFO_FILENAME);
        $file_ext = '.' . (pathinfo($originalFileName, PATHINFO_EXTENSION));

        ## Generate a new unique file name, you can modify this logic as per your requirements
        return 'UPLOAD_CSV_' . time() . $file_ext;
    }

    /**
     * Determines whether a directory can be accessed.
     *
     * is_executable() is not reliable on Windows prior PHP 5.0.0
     *  (http://www.php.net/manual/en/function.is-executable.php)
     * The following tests if the current OS is Windows and if so, merely
     * checks if the folder is writable;
     * otherwise, it checks additionally for executable status (like before).
     *
     * @param string $directory The target directory to test access
     */
    protected function isInaccessible($directory)
    {
        $isWin = $this->isWindows();
        $folderInaccessible = ($isWin) ? !is_writable($directory) : (!is_writable($directory) && !is_executable($directory));
        return $folderInaccessible;
    }

    /**
     * Determines is the OS is Windows or not
     *
     * @return boolean
     */

    protected function isWindows()
    {
        $isWin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
        return $isWin;
    }
}
