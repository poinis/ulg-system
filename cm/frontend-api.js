// frontend-api.js

const months = [
  'October 2025', 'November 2025', 'December 2025', 'January 2026', 'February 2026', 'March 2026',
  'April 2026', 'May 2026', 'June 2026', 'July 2026', 'August 2026', 'September 2026', 'October 2026'
];

const heroMonths = [0, 2, 4, 6, 8, 10, 12];

const coreDeliverables = [
  { name: 'T-Shirt #1', icon: 'tee', tasks: ['Design Started', 'First Draft', 'Revision Process', 'Final Design Approved'] },
  { name: 'T-Shirt #2', icon: 'tee', tasks: ['Design Started', 'First Draft', 'Revision Process', 'Final Design Approved'] },
  { name: 'T-Shirt #3', icon: 'tee', tasks: ['Design Started', 'First Draft', 'Revision Process', 'Final Design Approved'] },
  { name: 'Cap', icon: 'cap', tasks: ['Design Started', 'First Draft', 'Revision Process', 'Final Design Approved'] },
  { name: 'Tote Bag', icon: 'tote', tasks: ['Design Started', 'First Draft', 'Revision Process', 'Final Design Approved'] }
];

const heroDeliverables = [...coreDeliverables,
  { name: 'Hero Item 1', icon: 'hero', tasks: ['Design Started', 'First Draft', 'Revision Process', 'Final Design Approved'] },
  { name: 'Hero Item 2', icon: 'hero', tasks: ['Design Started', 'First Draft', 'Revision Process', 'Final Design Approved'] }
];

let capsuleData = {}; // ข้อมูลจาก backend
let currentMonthIndex = null; // ตัวแปรสำหรับเก็บว่ากำลังเปิดดูเดือนไหนอยู่

// ==========================================================
//  ส่วนของฟังก์ชันที่ย้ายขึ้นมาเพื่อป้องกัน ReferenceError
// ==========================================================

/**
 * ฟังก์ชันสำหรับปิดหน้าต่าง Modal และบันทึกข้อมูล
 */
async function closeModal() {
  const modal = document.getElementById('monthModal');
  if (currentMonthIndex !== null) {
    console.log('Saving data for month:', currentMonthIndex);
    await saveMonthData(currentMonthIndex); // บันทึกข้อมูลลง DB ตอนปิด
    currentMonthIndex = null;
    await generateCalendar(); // โหลดหน้าปฏิทินใหม่เพื่ออัปเดต Progress
  }
  modal.classList.add('hidden');
}

/**
 * ฟังก์ชันย่อยสำหรับอัปเดตข้อมูลใน Object `capsuleData` ทันที
 */
function updateDeliverableProp(dIndex, prop, value) {
  if (currentMonthIndex === null) return;
  capsuleData[currentMonthIndex].deliverables[dIndex][prop] = value;
  updateModalProgress();
}

function updateTaskStatus(dIndex, tIndex, isCompleted) {
  if (currentMonthIndex === null) return;
  capsuleData[currentMonthIndex].deliverables[dIndex].tasks[tIndex].completed = isCompleted;

  // อัปเดตการแสดงผล (ขีดฆ่าข้อความ)
  const taskLabel = document.querySelectorAll(`#deliverablesList > div`)[dIndex].querySelectorAll('ul li span')[tIndex];
  taskLabel.classList.toggle('completed', isCompleted);
  taskLabel.classList.toggle('text-gray-500', isCompleted);
  taskLabel.classList.toggle('text-gray-800', !isCompleted);
  updateModalProgress();
}

function updateModalProgress() {
  if (currentMonthIndex === null) return;
  const progress = calculateProgress(currentMonthIndex);
  document.getElementById('modalProgress').textContent = `${progress.completed} of ${progress.total} complete`;
  document.getElementById('modalProgressBar').style.width = `${progress.percentage}%`;
}

/**
 * ฟังก์ชันสำหรับเปิดหน้าต่าง Modal เพื่อแก้ไขข้อมูล
 */
function openMonthModal(monthIndex) {
  console.log('Opening modal for month:', monthIndex, months[monthIndex]); // Debug log
  currentMonthIndex = monthIndex;
  const data = capsuleData[monthIndex];
  if (!data) {
    console.error('No data found for month:', monthIndex);
    return;
  }

  const modal = document.getElementById('monthModal');
  document.getElementById('modalTitle').textContent = months[monthIndex];
  document.getElementById('modalSubtitle').textContent = heroMonths.includes(monthIndex) ? 'Hero Month' : 'Core Month';

  // เติมข้อมูล Theme/Notes เดิม
  const monthTheme = document.getElementById('monthTheme');
  monthTheme.value = data.theme || '';
  monthTheme.oninput = (e) => {
    capsuleData[currentMonthIndex].theme = e.target.value;
  };

  // สร้างรายการ Deliverables
  const deliverablesList = document.getElementById('deliverablesList');
  deliverablesList.innerHTML = ''; // ล้างข้อมูลเก่าทิ้งก่อน
  data.deliverables.forEach((d, dIndex) => {
    const deliverableEl = document.createElement('div');
    deliverableEl.className = 'p-4 border border-gray-200 rounded-lg';
    let tasksHtml = d.tasks.map((task, tIndex) => `
      <li class="task-item flex items-center">
        <label class="flex items-center w-full p-2 rounded-md hover:bg-gray-100 cursor-pointer">
          <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-gray-800 focus:ring-gray-700"
                 ${task.completed ? 'checked' : ''} onchange="updateTaskStatus(${dIndex}, ${tIndex}, this.checked)">
          <span class="ml-3 text-sm ${task.completed ? 'completed text-gray-500' : 'text-gray-800'}">${task.name}</span>
        </label>
      </li>
    `).join('');

    deliverableEl.innerHTML = `
      <div class="mb-4">
        <h4 class="text-md font-semibold text-gray-800 flex items-center mb-3">
          <span class="icon-${d.icon} text-xl mr-3"></span>
          ${d.name}
        </h4>
        <input type="text" class="w-full p-2 text-sm border border-gray-300 rounded-md mb-3"
               placeholder="Enter custom name..." value="${d.customName || ''}" oninput="updateDeliverableProp(${dIndex}, 'customName', this.value)">
      </div>
      
      <ul class="space-y-1 mb-4">${tasksHtml}</ul>
      
      <div class="grid grid-cols-2 gap-4 mb-4">
        <label class="flex items-center text-sm">
          <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-gray-800 focus:ring-gray-700"
                 ${d.priceReceived ? 'checked' : ''} onchange="updateDeliverableProp(${dIndex}, 'priceReceived', this.checked)">
          <span class="ml-2 text-gray-700">Price Received</span>
        </label>
        <label class="flex items-center text-sm">
          <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-gray-800 focus:ring-gray-700"
                 ${d.ordered ? 'checked' : ''} onchange="updateDeliverableProp(${dIndex}, 'ordered', this.checked)">
          <span class="ml-2 text-gray-700">Ordered</span>
        </label>
      </div>
      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Notes & Links</label>
        <textarea class="w-full p-2 text-sm border border-gray-300 rounded-md" rows="2"
                  placeholder="Add pricing links, artwork files, supplier info, or other notes..." oninput="updateDeliverableProp(${dIndex}, 'notes', this.value)">${d.notes || ''}</textarea>
      </div>
    `;
    deliverablesList.appendChild(deliverableEl);
  });

  updateModalProgress();
  modal.classList.remove('hidden');
}

// ==========================================================
//  ส่วนของฟังก์ชันที่ไม่ได้แก้ไข
// ==========================================================

async function fetchMonthData(monthIndex) {
  const res = await fetch(`api/get_data.php?monthIndex=${monthIndex}`);
  return await res.json();
}

async function initMonthData(monthIndex) {
  const isHero = heroMonths.includes(monthIndex);
  const deliverables = isHero ? heroDeliverables : coreDeliverables;

  await fetch('api/init_data.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ monthIndex, deliverables })
  });
}

async function saveMonthData(monthIndex) {
  await fetch('api/save_data.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      monthIndex,
      theme: capsuleData[monthIndex].theme,
      deliverables: capsuleData[monthIndex].deliverables
    })
  });
}

async function toggleItem(monthIndex, deliverableIndex, type, taskIndex = null, value = true) {
  await fetch('api/toggle_task.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ monthIndex, deliverableIndex, type, taskIndex, value })
  });
}

function calculateProgress(monthIndex) {
  const month = capsuleData[monthIndex];
  if (!month) return { completed: 0, total: 0, percentage: 0 };

  let completed = 0, total = 0;
  month.deliverables.forEach(d => {
    d.tasks.forEach(t => { total++; if (t.completed) completed++; });
    total += 2;
    if (d.priceReceived) completed++;
    if (d.ordered) completed++;
  });
  return {
    completed, total,
    percentage: total > 0 ? Math.round((completed / total) * 100) : 0
  };
}

async function generateCalendar() {
  const container = document.getElementById('calendarGrid');
  container.innerHTML = ''; // ล้างข้อมูลเก่าก่อน
  
  console.log('Generating calendar for', months.length, 'months'); // Debug log
  console.log('Months array:', months); // Debug: ตรวจสอบ array

  for (let i = 0; i < months.length; i++) {
    console.log(`Processing month ${i}: ${months[i]}`); // Debug log
    
    try {
      let data = await fetchMonthData(i);
      if (!data.exists) {
        await initMonthData(i);
        data = await fetchMonthData(i);
      }
      
      // ตรวจสอบให้แน่ใจว่า capsuleData[i] มีข้อมูลถูกต้อง
      capsuleData[i] = {
        theme: data.theme || '',
        deliverables: data.deliverables || []
      };

      const isHero = heroMonths.includes(i);
      const progress = calculateProgress(i);

      const monthCard = document.createElement('div');
      monthCard.className = `month-card p-6 rounded-lg cursor-pointer ${isHero ? 'hero-month' : 'core-month'}`;
      monthCard.setAttribute('data-month-index', i); // เพิ่ม attribute เพื่อ debug
      
      // ใช้ addEventListener แทน .onclick และใช้ closure เพื่อ capture ค่า i
      monthCard.addEventListener('click', (function(monthIndex) {
        return function() {
          console.log('Card clicked for month:', monthIndex, months[monthIndex]); // Debug log
          openMonthModal(monthIndex);
        };
      })(i));

      monthCard.innerHTML = `
        <div class="flex justify-between items-start mb-4">
          <div>
            <h3 class="text-lg font-semibold ${isHero ? 'text-white' : 'text-gray-900'}">${months[i]}</h3>
            <p class="text-sm ${isHero ? 'text-gray-300' : 'text-gray-600'}">${isHero ? 'Hero Month' : 'Core Month'}</p>
            <p class="text-xs ${isHero ? 'text-gray-400' : 'text-gray-500'}">Index: ${i}</p>
          </div>
          <div class="text-right">
            <div class="text-2xl font-bold ${isHero ? 'text-white' : 'text-gray-900'}">${progress.percentage}%</div>
          </div>
        </div>
        <div class="mb-4">
          <div class="flex space-x-2 mb-2">
            ${(isHero ? heroDeliverables : coreDeliverables).map(d => `<span class="icon-${d.icon} text-lg"></span>`).join('')}
          </div>
          <div class="w-full ${isHero ? 'bg-gray-600' : 'bg-gray-200'} rounded-full h-2">
            <div class="progress-bar ${isHero ? 'bg-white' : 'bg-gray-900'} h-2 rounded-full" style="width: ${progress.percentage}%"></div>
          </div>
        </div>
        <div class="text-sm ${isHero ? 'text-gray-300' : 'text-gray-600'}">
          ${progress.completed} of ${progress.total} tasks complete
        </div>
      `;

      container.appendChild(monthCard);
      console.log(`Added card for month ${i}: ${months[i]} (Element count: ${container.children.length})`); // Debug log
      
    } catch (error) {
      console.error(`Error processing month ${i}:`, error);
    }
  }

  console.log('Calendar generation complete. Total cards:', container.children.length); // Debug log
  
  updateOverallProgress();
  checkUpcomingHeroMonths();
}

function updateOverallProgress() {
  let totalCompleted = 0, totalTasks = 0;
  months.forEach((_, i) => {
    const p = calculateProgress(i);
    totalCompleted += p.completed;
    totalTasks += p.total;
  });
  const overall = totalTasks > 0 ? Math.round((totalCompleted / totalTasks) * 100) : 0;
  document.getElementById('overallProgress').textContent = `${overall}%`;
}

function checkUpcomingHeroMonths() {
  const banner = document.getElementById('notificationBanner');
  const text = document.getElementById('notificationText');
  const next = heroMonths.find(index => calculateProgress(index).percentage < 100);
  if (next !== undefined) {
    text.textContent = `Upcoming hero drop: ${months[next]} - Start preparing deliverables!`;
    banner.classList.remove('hidden');
  }
}

// Event Listeners
document.getElementById('monthModal').addEventListener('click', e => {
  if (e.target.id === 'monthModal') closeModal();
});

// เรียกใช้ generateCalendar เมื่อ DOM โหลดเสร็จ (แบบเดียวเท่านั้น)
document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM loaded, generating calendar...');
  generateCalendar();
});