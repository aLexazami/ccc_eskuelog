<?php

function render_page_header(string $fallbackTitle = '', array $breadcrumbs = [], string $apiKey = 'none'): string
{
    $userVal = strtoupper($_GET[$apiKey] ?? '');
    $title = ACCESS_NAME[$userVal] ?? $fallbackTitle;
    $items = !empty($breadcrumbs) ? $breadcrumbs : [['label' => $title, 'url' => '']];

    $html = '<div class="page-header">';
    $html .= '<h3 class="fw-bold mb-3">' . htmlspecialchars($title) . '</h3>';
    $html .= '<ul class="breadcrumbs mb-3">';
    $html .= '<li class="nav-home">';
    $html .= '<a href="' . BASE_URL . 'Admin/">';
    $html .= '<i class="icon-home"></i>';
    $html .= '</a>';
    $html .= '</li>';

    foreach ($items as $crumb) {
        $url = !empty($crumb['url']) ? htmlspecialchars($crumb['url']) : '#';
        $label = htmlspecialchars($crumb['label']);

        $html .= '<li class="separator"><i class="icon-arrow-right"></i></li>';
        $html .= '<li class="nav-item">';
        $html .= '<a href="' . $url . '">' . $label . '</a>';
        $html .= '</li>';
    }

    $html .= '</ul>';
    $html .= '</div>';

    return $html;
}
