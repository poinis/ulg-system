// สลับแท็บ
document.querySelectorAll('.mode-tabs li').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.mode-tabs li').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.mode-form').forEach(f => f.classList.remove('active'));
    
    tab.classList.add('active');
    document.getElementById('form-' + tab.dataset.mode).classList.add('active');
  });
});

// แสดงการโหลดเมื่อ submit
document.querySelectorAll('.mode-form').forEach(form => {
  form.addEventListener('submit', function(e) {
    const btn = this.querySelector('.btn-generate');
    btn.textContent = '⏳ กำลังสร้าง...';
    btn.disabled = true;
  });
});
