/* =====================================================
   DATA CONFIGURATION & BUILDING MAPPING
   ===================================================== */
const officeBuildingMap = {
  "Admissions Office": "Admin Building",
  "Accounting Office": "Admin Building",
  "Cashier": "Admin Building",
  "Human Resources": "Admin Building",
  "College of Business & Accountancy": "Admin Building",
  "College of Education": "Admin Building",
  "IT Center": "Admin Building",
  "MIS / IT Support": "Admin Building",
  "Student Affairs": "Admin Building",
  "Property & Supply Office": "Admin Building",
  "Guidance Office": "Admin Building",
  "School Clinic": "Admin Building",
  "Dean's Office": "Admin Building",
  "VPAA Office": "Admin Building",
  "College of Computer Studies": "Rizal Building",
  "College of Industrial Technology": "Rizal Building",
  "Library": "JMC Building",
  "Registrar Office": "JMC Building"
};

const officeMetaDefaults = {
  "Registrar Office": { icon: "bi-file-earmark-text-fill", color: "text-primary bg-primary-subtle" },
  "Accounting Office": { icon: "bi-calculator-fill", color: "text-success bg-success-subtle" },
  "Admissions Office": { icon: "bi-mortarboard-fill", color: "text-warning bg-warning-subtle" },
  "Guidance Office": { icon: "bi-people-fill", color: "text-info bg-info-subtle" },
  "School Clinic": { icon: "bi-hospital-fill", color: "text-danger bg-danger-subtle" },
  "Library": { icon: "bi-book-fill", color: "text-secondary bg-secondary-subtle" },
  "Cashier": { icon: "bi-cash-coin", color: "text-success bg-success-subtle" },
  "Human Resources": { icon: "bi-briefcase-fill", color: "text-dark bg-light-subtle" },
  "IT Center": { icon: "bi-laptop-fill", color: "text-primary bg-primary-subtle" },
  "Student Affairs": { icon: "bi-person-badge-fill", color: "text-info bg-info-subtle" },
  "VPAA Office": { icon: "bi-award-fill", color: "text-warning bg-warning-subtle" },
  "Dean's Office": { icon: "bi-building-gear", color: "text-danger bg-danger-subtle" },
  "College of Business & Accountancy": { icon: "bi-briefcase-fill", color: "text-primary bg-primary-subtle" },
  "College of Education": { icon: "bi-journal-bookmark-fill", color: "text-success bg-success-subtle" },
  "College of Computer Studies": { icon: "bi-cpu-fill", color: "text-info bg-info-subtle" },
  "College of Industrial Technology": { icon: "bi-tools", color: "text-warning bg-warning-subtle" },
  "MIS / IT Support": { icon: "bi-headset", color: "text-primary bg-primary-subtle" },
  "Property & Supply Office": { icon: "bi-box-seam-fill", color: "text-secondary bg-secondary-subtle" },
};

/* =====================================================
   ACRONYM & TEXT SHORTENING HELPER
   ===================================================== */
const officeAcronyms = {
  "College of Business & Accountancy": "CBA Office",
  "College of Education": "CED Office",
  "College of Computer Studies": "CCS Office",
  "College of Industrial Technology": "CIT Office",
  "MIS / IT Support": "M.I.S.D Office",
  "Property & Supply Office": "PSO",
  "Human Resources": "HR Office",
  "Student Affairs": "OSA"
};

function getShortName(fullName, maxLength = 20) {
  if (officeAcronyms[fullName]) {
    return officeAcronyms[fullName];
  }
  return fullName.length > maxLength ? fullName.substring(0, maxLength - 3) + "..." : fullName;
}

let queueData = [
  { number: "R-024", office: "Registrar Office" },
  { number: "AC-015", office: "Accounting Office" },
  { number: "AD-008", office: "Admissions Office" },
  { number: "G-011", office: "Guidance Office" },
  { number: "L-012", office: "Library" },
  { number: "C-006", office: "School Clinic" },
  { number: "CA-018", office: "Cashier" },
  { number: "HR-004", office: "Human Resources" },
  { number: "IT-009", office: "IT Center" },
  { number: "SA-021", office: "Student Affairs" },
  { number: "VP-003", office: "VPAA Office" },
  { number: "DO-001", office: "Dean's Office" },
  { number: "CBA-005", office: "College of Business & Accountancy" },
  { number: "CED-003", office: "College of Education" },
  { number: "CCS-010", office: "College of Computer Studies" },
  { number: "CIT-007", office: "College of Industrial Technology" },
  { number: "MISD-002", office: "MIS / IT Support" },
  { number: "PSO-004", office: "Property & Supply Office" },
];

let officeStatus = [];
let currentIndex = 0;

// CAROUSEL PAGING SETTINGS FOR OFFICES
let currentOfficePage = 0;
const OFFICES_PER_PAGE = 12;

/* =====================================================
   INITIALIZE DEPARTMENTS DIRECTLY FROM PHP GLOBAL
   ===================================================== */
function initOfficesFromPhp() {
  const data = window.DB_DEPARTMENTS;

  if (Array.isArray(data) && data.length > 0) {
    officeStatus = data.map(dept => {
      const name = dept.department_name || dept.name || "Office";
      const meta = officeMetaDefaults[name] || {
        icon: "bi-building-fill",
        color: "text-primary bg-primary-subtle"
      };
      return {
        office: name,
        queue: dept.current_ticket || "---",
        status: dept.status || "SERVING",
        icon: meta.icon,
        color: meta.color,
        counter: dept.counter_name || ""
      };
    });
  } else {
    loadFallbackOffices();
  }
  
  updateOfficeStatus();
}

function loadFallbackOffices() {
  const officeList = [
    "Registrar Office", "Accounting Office", "Admissions Office", "Guidance Office", 
    "School Clinic", "Library", "Cashier", "Human Resources", "IT Center", 
    "Student Affairs", "VPAA Office", "Dean's Office", 
    "College of Business & Accountancy", "College of Education", "College of Computer Studies", 
    "College of Industrial Technology", "MIS / IT Support", "Property & Supply Office"
  ];

  officeStatus = officeList.map(name => {
    const meta = officeMetaDefaults[name] || { icon: "bi-building-fill", color: "text-primary bg-primary-subtle" };
    const qItem = queueData.find(q => q.office === name);
    return {
      office: name,
      queue: qItem ? qItem.number : "---",
      status: "SERVING",
      icon: meta.icon,
      color: meta.color
    };
  });
}

/* =====================================================
   VOICE ANNOUNCEMENT LOGIC
   ===================================================== */
let singleFemaleVoice = null;

function getBestFemaleVoice() {
  if (!('speechSynthesis' in window)) return null;
  const voices = window.speechSynthesis.getVoices();
  if (!voices || voices.length === 0) return null;

  const femaleNames = ["zira", "janny", "jenny", "samantha", "siri", "victoria", "karen", "aria", "ava", "emma", "hazel", "susan", "female", "google us english"];
  const maleNames = ["david", "mark", "george", "james", "richard", "male", "guy", "stefan"];

  let selected = voices.find(v => v.lang.startsWith('en') && femaleNames.some(fn => v.name.toLowerCase().includes(fn)));
  if (!selected) {
    selected = voices.find(v => v.lang.startsWith('en') && !maleNames.some(mn => v.name.toLowerCase().includes(mn)));
  }
  return selected || voices.find(v => v.lang.startsWith('en')) || voices[0];
}

function loadVoices() { singleFemaleVoice = getBestFemaleVoice(); }
if ('speechSynthesis' in window) {
  window.speechSynthesis.onvoiceschanged = loadVoices;
  loadVoices();
}

function playBellDing() {
  try {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;
    const ctx = new AudioContext();

    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(659.25, ctx.currentTime);
    gain1.gain.setValueAtTime(0.3, ctx.currentTime);
    gain1.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 1.2);

    osc1.connect(gain1);
    gain1.connect(ctx.destination);
    osc1.start(ctx.currentTime);
    osc1.stop(ctx.currentTime + 1.2);

    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.type = 'sine';
    osc2.frequency.setValueAtTime(783.99, ctx.currentTime + 0.25);
    gain2.gain.setValueAtTime(0.35, ctx.currentTime + 0.25);
    gain2.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 1.6);

    osc2.connect(gain2);
    gain2.connect(ctx.destination);
    osc2.start(ctx.currentTime + 0.25);
    osc2.stop(ctx.currentTime + 1.6);
  } catch (e) {
    console.warn("Audio Context blocked until page interaction.", e);
  }
}

function speakNowCalling(ticketNumber, officeName, buildingName) {
  if (!('speechSynthesis' in window)) return;
  window.speechSynthesis.cancel();
  if (!singleFemaleVoice) loadVoices();

  const spokenTicket = ticketNumber.split('').map(char => (char === '-' ? ' ' : char)).join(', ');
  
  const speechText = buildingName 
    ? `Now Calling, ${spokenTicket}, please proceed to ${officeName} at ${buildingName}.`
    : `Now Calling, ${spokenTicket}, please proceed to ${officeName}.`;

  const utterance = new SpeechSynthesisUtterance(speechText);
  utterance.rate = 0.88;
  utterance.pitch = 1.1;
  utterance.lang = 'en-US';
  if (singleFemaleVoice) utterance.voice = singleFemaleVoice;

  setTimeout(() => window.speechSynthesis.speak(utterance), 500);
}

/* =====================================================
   DOM UPDATES WITH CAROUSEL SUPPORT & SHORT NAMES
   ===================================================== */
function updateCallingQueue() {
  if (!Array.isArray(queueData) || queueData.length === 0 || !queueData[currentIndex]) {
    const currentNumberEl = document.getElementById("currentNumber");
    const servingOfficeEl = document.getElementById("servingOffice");
    const servingBuildingEl = document.getElementById("servingBuilding");
    const queueCountEl = document.getElementById("queueCount");

    if (currentNumberEl) currentNumberEl.textContent = "---";
    if (servingOfficeEl) servingOfficeEl.textContent = "NO ACTIVE CALLS";
    if (servingBuildingEl) servingBuildingEl.textContent = "---";
    if (queueCountEl) queueCountEl.textContent = "0 WAITING";
    return;
  }

  const current = queueData[currentIndex];
  const buildingName = officeBuildingMap[current.office] || "Main Campus";

  const currentNumberEl = document.getElementById("currentNumber");
  const servingOfficeEl = document.getElementById("servingOffice");
  const servingBuildingEl = document.getElementById("servingBuilding");

  if (currentNumberEl) currentNumberEl.textContent = current.number || "---";
  if (servingOfficeEl) servingOfficeEl.textContent = current.office || "---";
  if (servingBuildingEl) servingBuildingEl.textContent = buildingName;

  if (typeof playBellDing === "function") playBellDing();
  if (typeof speakNowCalling === "function") {
    speakNowCalling(current.number, current.office, buildingName);
  }

  // Update waiting sidebar
  const waitingList = document.getElementById("waitingList");
  if (waitingList) {
    waitingList.innerHTML = "";
    const fragment = document.createDocumentFragment();

    for (let i = 1; i < queueData.length; i++) {
      const index = (currentIndex + i) % queueData.length;
      const queue = queueData[index];

      const item = document.createElement("div");
      item.className = "waiting-item d-flex justify-content-between align-items-center p-2 mb-2 rounded-3";
      item.innerHTML = `
        <span class="token-pill">${queue.number || "---"}</span>
        <span class="fw-bold text-dark small ms-2 text-truncate" title="${queue.office}">${getShortName(queue.office, 18)}</span>
      `;
      fragment.appendChild(item);
    }
    waitingList.appendChild(fragment);
  }

  const queueCountEl = document.getElementById("queueCount");
  if (queueCountEl) {
    queueCountEl.textContent = `${Math.max(0, queueData.length - 1)} WAITING`;
  }

  // Sync ticket into officeStatus array
  const targetOffice = officeStatus.find(o => o.office === current.office);
  if (targetOffice) {
    targetOffice.queue = current.number;
  }

  rotateOfficePage();
  updateOfficeStatus();
}

function rotateOfficePage() {
  if (officeStatus.length <= OFFICES_PER_PAGE) return;
  const totalPages = Math.ceil(officeStatus.length / OFFICES_PER_PAGE);
  currentOfficePage = (currentOfficePage + 1) % totalPages;
}

function updateOfficeStatus() {
  const grid = document.getElementById("officeGrid");
  const subtitle = document.getElementById("officeSubtitle");
  
  if (!grid) return;
  grid.innerHTML = "";

  const totalOffices = officeStatus.length;
  const currentTicket = queueData[currentIndex] ? queueData[currentIndex].number : "";
  const currentOfficeName = queueData[currentIndex] ? queueData[currentIndex].office : "";

  // Auto-jump to active office page if it lives on another page
  const callingOfficeIndex = officeStatus.findIndex(o => o.office === currentOfficeName);
  if (callingOfficeIndex !== -1) {
    currentOfficePage = Math.floor(callingOfficeIndex / OFFICES_PER_PAGE);
  }

  const totalPages = Math.ceil(totalOffices / OFFICES_PER_PAGE);
  if (subtitle) {
    const pageIndicator = totalPages > 1 ? ` (PG ${currentOfficePage + 1}/${totalPages})` : '';
    subtitle.textContent = `${totalOffices} OFFICE${totalOffices === 1 ? '' : 'S'}${pageIndicator}`;
  }

  const startIndex = currentOfficePage * OFFICES_PER_PAGE;
  const visibleOffices = officeStatus.slice(startIndex, startIndex + OFFICES_PER_PAGE);

  visibleOffices.forEach(office => {
    const isNowCalling = office.office === currentOfficeName && currentTicket !== "";
    const displayStatus = isNowCalling ? "NOW CALLING" : office.status;
    const badgeColor = isNowCalling ? "text-warning" : "text-success";

    const counterBadge = office.counter 
      ? `<span class="badge bg-secondary-subtle text-dark ms-1" style="font-size: 0.55rem;">${office.counter}</span>` 
      : "";

    const shortTitle = getShortName(office.office, 20);

    const card = document.createElement("div");
    card.className = `office-status-card office-compact-card w-100 d-flex align-items-center gap-1 rounded-2 border ${isNowCalling ? 'active-calling border-warning bg-warning-subtle' : 'bg-white'}`;

    card.innerHTML = `
      <div class="icon-circle rounded-circle ${office.color} d-flex align-items-center justify-content-center flex-shrink-0">
        <i class="bi ${office.icon || 'bi-building'}"></i>
      </div>
      <div class="overflow-hidden flex-grow-1 d-flex flex-column align-items-start">
        <div class="fw-bold text-dark office-title text-truncate w-100" title="${office.office}">${shortTitle}</div>
        <div class="d-flex align-items-center">
          <span class="fw-extrabold text-primary office-ticket">${office.queue}</span>
          ${counterBadge}
        </div>
        <span class="status-indicator ${badgeColor} d-block mt-1">
          <i class="bi bi-circle-fill me-1" style="font-size:0.35rem;"></i>${displayStatus}
        </span>
      </div>
    `;

    grid.appendChild(card);
  });
}

function updateClock() {
  const now = new Date();
  let hours = now.getHours();
  let minutes = now.getMinutes();
  const ampm = hours >= 12 ? "PM" : "AM";
  hours = hours % 12 || 12;
  minutes = minutes.toString().padStart(2, "0");

  const clockEl = document.getElementById("headerClock");
  if (clockEl) clockEl.textContent = `${hours}:${minutes} ${ampm}`;

  const options = { weekday: "short", month: "long", day: "numeric", year: "numeric" };
  const dateEl = document.getElementById("headerDate");
  if (dateEl) dateEl.textContent = now.toLocaleDateString("en-US", options).toUpperCase();
}

/* =====================================================
   INIT & INTERVALS
   ===================================================== */
updateClock();
setInterval(updateClock, 1000);

initOfficesFromPhp();

setTimeout(() => {
  updateCallingQueue();
  setInterval(() => {
    currentIndex = (currentIndex + 1) % queueData.length;
    updateCallingQueue();
  }, 10000);
}, 300);

function initTickerCycle() {
  const items = document.querySelectorAll('#tickerContainer .ticker-item');
  if (items.length <= 1) return;

  let activeIndex = 0;

  setInterval(() => {
    const currentItem = items[activeIndex];
    
    currentItem.classList.remove('active');
    currentItem.classList.add('exit');

    activeIndex = (activeIndex + 1) % items.length;
    const nextItem = items[activeIndex];

    nextItem.classList.remove('exit');
    nextItem.classList.add('active');

    setTimeout(() => {
      currentItem.classList.remove('exit');
    }, 500);

  }, 6000);
}

document.addEventListener('DOMContentLoaded', initTickerCycle);