/* ===== TOAST ===== */
function showToast(msg,type){
  var existing=document.querySelector('.toast');
  if(existing)existing.remove();
  var t=document.createElement('div');
  t.className='toast '+(type||'info');
  var icon=type==='success'?'check-circle':type==='error'?'exclamation-circle':'info-circle';
  t.innerHTML='<i class="fas fa-'+icon+'"></i> '+msg;
  document.body.appendChild(t);
  setTimeout(function(){t.classList.add('out');setTimeout(function(){t.remove();},300);},3000);
}