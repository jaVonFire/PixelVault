/* ===== NAVEGACION ===== */
function pageUrl(name){
  var map={home:'index.html',products:'views/products.html',auth:'views/account.html',profile:'views/profile.html',about:'views/about.html',admin:'views/admin.html'};
  var target=map[name]||'index.html';
  if(/[\/\\]views[\/\\]/.test(window.location.href)){
    return name==='home'?'../index.html':target.replace('views/','');
  }
  return target;
}
function showSection(name,tab){
  if(name==='profile'&&!currentUser){
    showToast(currentLang()==='es'?'Inicia sesion para ver tu perfil':'Log in to see your profile','error');
    name='auth';tab=null;
  }
  closeCart();
  window.location.href=pageUrl(name)+(tab?'?tab='+encodeURIComponent(tab):'');
}