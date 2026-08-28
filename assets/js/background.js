/* ===== FONDO ANIMADO ===== */
var canvas=document.getElementById('bgCanvas');
var ctx=canvas.getContext('2d');
var particles=[];
function resizeCanvas(){canvas.width=window.innerWidth;canvas.height=window.innerHeight;}
function initParticles(){
  particles=[];
  var count=Math.floor((canvas.width*canvas.height)/14000);
  for(var i=0;i<count;i++)particles.push({x:Math.random()*canvas.width,y:Math.random()*canvas.height,size:Math.random()*2+1,speedY:-(Math.random()*0.3+0.1),speedX:(Math.random()-0.5)*0.2,opacity:Math.random()*0.4+0.1,color:Math.random()>0.6?'#2563EB':Math.random()>0.5?'#DC2626':'#1D4ED8',pulse:Math.random()*Math.PI*2});
}
function animateBg(){
  ctx.clearRect(0,0,canvas.width,canvas.height);
  ctx.strokeStyle='rgba(37,99,235,0.03)';ctx.lineWidth=1;
  for(var x=0;x<canvas.width;x+=60){ctx.beginPath();ctx.moveTo(x,0);ctx.lineTo(x,canvas.height);ctx.stroke();}
  for(var y=0;y<canvas.height;y+=60){ctx.beginPath();ctx.moveTo(0,y);ctx.lineTo(canvas.width,y);ctx.stroke();}
  for(var i=0;i<particles.length;i++){
    var p=particles[i];
    p.x+=p.speedX;p.y+=p.speedY;p.pulse+=0.02;
    if(p.y<-10){p.y=canvas.height+10;p.x=Math.random()*canvas.width;}
    if(p.x<-10)p.x=canvas.width+10;
    if(p.x>canvas.width+10)p.x=-10;
    ctx.fillStyle=p.color;ctx.globalAlpha=p.opacity*(0.6+0.4*Math.sin(p.pulse));
    ctx.fillRect(Math.round(p.x),Math.round(p.y),Math.round(p.size),Math.round(p.size));
  }
  ctx.globalAlpha=1;
  requestAnimationFrame(animateBg);
}
window.addEventListener('resize',function(){resizeCanvas();initParticles();});
resizeCanvas();initParticles();animateBg();