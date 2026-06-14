// GlowSkin Clinic Dashboard Interactive UI Helper
document.addEventListener('DOMContentLoaded', () => {
  // 1. Inject Toast Container and Modal HTML
  const modalContainer = document.createElement('div');
  modalContainer.innerHTML = `
    <!-- Modal New Appointment -->
    <div id="appointment-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
      <div class="bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant/30 dark:border-slate-800 rounded-2xl w-full max-w-md p-xl shadow-2xl transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center mb-lg">
          <h3 class="font-headline-sm text-headline-sm text-on-surface dark:text-slate-100">Tambah Reservasi Baru</h3>
          <button id="close-appointment-modal" class="text-on-surface-variant dark:text-slate-400 hover:text-primary transition-colors flex items-center">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <form id="appointment-form" class="space-y-md">
          <div>
            <label class="block text-body-sm font-medium text-on-surface-variant dark:text-slate-300 mb-xs">Nama Pasien</label>
            <input type="text" id="appt-patient" required class="w-full px-md py-sm bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg text-body-sm text-on-surface dark:text-slate-100 outline-none focus:border-primary transition-all" placeholder="Masukkan nama lengkap" />
          </div>
          <div>
            <label class="block text-body-sm font-medium text-on-surface-variant dark:text-slate-300 mb-xs">No. Telepon</label>
            <input type="tel" id="appt-phone" required class="w-full px-md py-sm bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg text-body-sm text-on-surface dark:text-slate-100 outline-none focus:border-primary transition-all" placeholder="Contoh: 08123456789" />
          </div>
          <div class="grid grid-cols-2 gap-sm">
            <div>
              <label class="block text-body-sm font-medium text-on-surface-variant dark:text-slate-300 mb-xs">Layanan</label>
              <select id="appt-service" class="w-full px-md py-sm bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg text-body-sm text-on-surface dark:text-slate-100 outline-none focus:border-primary transition-all">
                <option>Consultation</option>
                <option>Facial Treatment</option>
                <option>Laser Therapy</option>
                <option>Peeling Treatment</option>
              </select>
            </div>
            <div>
              <label class="block text-body-sm font-medium text-on-surface-variant dark:text-slate-300 mb-xs">Dokter</label>
              <select id="appt-doctor" class="w-full px-md py-sm bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg text-body-sm text-on-surface dark:text-slate-100 outline-none focus:border-primary transition-all">
                <option>dr. Sarah, Sp.KK</option>
                <option>dr. Adrian</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-body-sm font-medium text-on-surface-variant dark:text-slate-300 mb-xs">Tanggal & Waktu</label>
            <input type="datetime-local" id="appt-time" required class="w-full px-md py-sm bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg text-body-sm text-on-surface dark:text-slate-100 outline-none focus:border-primary transition-all" />
          </div>
          <div class="flex justify-end gap-sm pt-md border-t border-outline-variant/30 dark:border-slate-800">
            <button type="button" id="cancel-appointment-modal" class="px-md py-sm border border-outline-variant dark:border-slate-700 text-on-surface-variant dark:text-slate-300 rounded-lg text-body-sm hover:bg-surface-container-low dark:hover:bg-slate-800 transition-all">Batal</button>
            <button type="submit" class="px-md py-sm bg-primary text-on-primary rounded-lg text-body-sm hover:brightness-110 transition-all">Simpan Reservasi</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Settings -->
    <div id="settings-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
      <div class="bg-surface-container-lowest dark:bg-[#1e293b] border border-outline-variant dark:border-slate-800 rounded-2xl w-full max-w-md p-xl shadow-2xl transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center mb-lg">
          <h3 class="font-headline-sm text-headline-sm text-on-surface dark:text-slate-100">Pengaturan Dashboard</h3>
          <button id="close-settings-modal" class="text-on-surface-variant dark:text-slate-400 hover:text-primary transition-colors flex items-center">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="space-y-md">
          <div>
            <h4 class="text-body-md font-bold text-primary mb-xs">Informasi Klinik</h4>
            <div class="space-y-sm">
              <div>
                <label class="block text-body-sm text-on-surface-variant dark:text-slate-400 mb-1">Nama Klinik</label>
                <input type="text" class="w-full px-md py-sm bg-surface dark:bg-slate-800 border border-outline-variant dark:border-slate-700 rounded-lg text-body-sm text-on-surface dark:text-slate-100 outline-none" value="GlowSkin Clinic" readonly />
              </div>
              <div>
                <label class="block text-body-sm text-on-surface-variant dark:text-slate-400 mb-1">Sistem Keamanan</label>
                <div class="flex items-center justify-between py-xs border-b border-outline-variant/30 dark:border-slate-800">
                  <span class="text-body-sm text-on-surface dark:text-slate-300">Enkripsi Database SSL</span>
                  <span class="px-sm py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-[10px] font-bold rounded-full">AKTIF</span>
                </div>
              </div>
            </div>
          </div>
          <div>
            <h4 class="text-body-md font-bold text-primary mb-xs">Preferensi Sistem</h4>
            <div class="space-y-sm">
              <div class="flex items-center justify-between">
                <span class="text-body-sm text-on-surface dark:text-slate-300">Kirim Notifikasi WhatsApp</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" checked class="sr-only peer" />
                  <div class="w-9 h-5 bg-outline-variant dark:bg-slate-700 rounded-full peer peer-focus:ring-2 peer-focus:ring-primary/20 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                </label>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-body-sm text-on-surface dark:text-slate-300">Backup Otomatis Harian</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" checked class="sr-only peer" />
                  <div class="w-9 h-5 bg-outline-variant dark:bg-slate-700 rounded-full peer peer-focus:ring-2 peer-focus:ring-primary/20 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                </label>
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-sm pt-md border-t border-outline-variant/30 dark:border-slate-800">
            <button type="button" id="save-settings-btn" class="px-md py-sm bg-primary text-on-primary rounded-lg text-body-sm hover:brightness-110 transition-all">Simpan Pengaturan</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-lg right-lg z-[100] space-y-sm"></div>
  `;
  document.body.appendChild(modalContainer);

  // 2. Setup Modal references
  const apptModal = document.getElementById('appointment-modal');
  const apptForm = document.getElementById('appointment-form');
  const settingsModal = document.getElementById('settings-modal');

  // 3. Helper functions for animation open/close
  function openModal(modal) {
    modal.classList.remove('hidden');
    // For transition effect
    setTimeout(() => {
      modal.classList.remove('opacity-0');
      const inner = modal.querySelector('div');
      if (inner) inner.classList.remove('scale-95');
    }, 10);
  }

  function closeModal(modal) {
    modal.classList.add('opacity-0');
    const inner = modal.querySelector('div');
    if (inner) inner.classList.add('scale-95');
    setTimeout(() => {
      modal.classList.add('hidden');
    }, 300);
  }

  // Toast function
  window.showToast = function(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `flex items-center gap-sm px-md py-sm bg-surface-container-lowest dark:bg-slate-850 border-l-4 ${type === 'success' ? 'border-primary' : 'border-error'} rounded-lg shadow-lg text-body-sm text-on-surface dark:text-slate-100 transform translate-y-2 opacity-0 transition-all duration-300`;
    toast.innerHTML = `
      <span class="material-symbols-outlined text-[20px] ${type === 'success' ? 'text-primary' : 'text-error'}">
        ${type === 'success' ? 'check_circle' : 'error'}
      </span>
      <span>${message}</span>
    `;
    container.appendChild(toast);
    setTimeout(() => {
      toast.classList.remove('translate-y-2', 'opacity-0');
    }, 10);
    setTimeout(() => {
      toast.classList.add('translate-y-2', 'opacity-0');
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  }

  // 4. Attach event listeners
  // New Appointment Button click
  const newApptBtns = document.querySelectorAll('#btn-new-appointment, .btn-new-appointment');
  newApptBtns.forEach(btn => {
    btn.addEventListener('click', () => openModal(apptModal));
  });

  // Settings Link click
  const settingsLinks = document.querySelectorAll('#link-settings, .link-settings');
  settingsLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      openModal(settingsModal);
    });
  });

  // Close buttons
  document.getElementById('close-appointment-modal')?.addEventListener('click', () => closeModal(apptModal));
  document.getElementById('cancel-appointment-modal')?.addEventListener('click', () => closeModal(apptModal));
  document.getElementById('close-settings-modal')?.addEventListener('click', () => closeModal(settingsModal));

  // Save Settings Button
  document.getElementById('save-settings-btn')?.addEventListener('click', () => {
    closeModal(settingsModal);
    showToast('Pengaturan berhasil diperbarui!');
  });

  // Form submission handling for New Appointment
  apptForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    const patient = document.getElementById('appt-patient').value;
    const phone = document.getElementById('appt-phone').value;
    const service = document.getElementById('appt-service').value;
    const doctor = document.getElementById('appt-doctor').value;
    const timeVal = document.getElementById('appt-time').value;

    closeModal(apptModal);
    showToast(`Reservasi atas nama ${patient} berhasil disimpan!`);
    apptForm.reset();

    // Dynamically insert into the table if it's index.html
    const tbody = document.querySelector('table tbody');
    if (tbody && (window.location.pathname.includes('index.html') || window.location.pathname.endsWith('/dashboard-admin/'))) {
      const tr = document.createElement('tr');
      tr.className = "text-on-surface-variant dark:text-slate-300 border-b border-outline-variant/10 dark:border-slate-800/30";
      
      // Format time (e.g. "2026-06-14T10:00" -> "14 Jun 2026, 10:00")
      let formattedTime = timeVal;
      if (timeVal) {
        const dateObj = new Date(timeVal);
        const options = { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        formattedTime = dateObj.toLocaleDateString('id-ID', options).replace(/\./g, ':');
      }

      tr.innerHTML = `
        <td class="py-md font-bold text-on-surface dark:text-slate-100">${patient}</td>
        <td class="py-md">${phone}</td>
        <td class="py-md">${service}</td>
        <td class="py-md">${doctor}</td>
        <td class="py-md">${formattedTime}</td>
        <td class="py-md">
          <span class="inline-flex items-center gap-xs px-sm py-1 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 font-bold text-[12px]">
            Terkonfirmasi
          </span>
        </td>
      `;
      tbody.insertBefore(tr, tbody.firstChild);
    }
  });
});
