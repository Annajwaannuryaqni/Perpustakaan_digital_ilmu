document.addEventListener("DOMContentLoaded",()=>{

const audio=document.getElementById("notificationSound");
const toastBox=document.getElementById("toast-container");

let lastUnread=0;

let unlocked=false;
function unlock(){
if(unlocked||!audio)return;
audio.play().then(()=>{
audio.pause();
audio.currentTime=0;
unlocked=true;
}).catch(()=>{});
}
document.addEventListener("click",unlock);
document.addEventListener("keydown",unlock);
function playSound(){
if(!audio)return;
audio.pause();
audio.currentTime=0;
audio.play().catch(()=>{});
}
window.showToast=function(
title,
message,
type="info",
icon="fa-info-circle",
color="#00bcd4"
){
if(!toastBox)return;
playSound();
const toast=document.createElement("div");
toast.className="toast-modern";
toast.innerHTML=`
<div class="toast-icon" style="color:${color}">
<i class="fas ${icon}"></i>
</div>
<div class="toast-body">
<div class="toast-title">${title}</div>
<div class="toast-message">${message}</div>
</div>
<button class="toast-close">&times;</button>
<div class="toast-progress" style="background:${color}"></div>
`;
toastBox.appendChild(toast);
const close=()=>{
toast.classList.add("fade-out");
setTimeout(()=>toast.remove(),350);
};
toast.querySelector(".toast-close").onclick=close;
let timer=setTimeout(close,5000);
toast.onmouseenter=()=>clearTimeout(timer);
toast.onmouseleave=()=>timer=setTimeout(close,2500);
};
function fetchNotif(){
fetch("../api/notifications.php?action=get")
.then(r=>r.json())
.then(d=>{
if(d.status!=="success")return;
if(d.unread_count>lastUnread){
const n=d.notifications.find(x=>x.is_read==0);
if(n){
showToast(n.title,n.message,"info",n.icon,n.color);
if(window.Notification&&Notification.permission==="granted"){
new Notification(n.title,{
body:n.message,
icon:"../assets/img/logo.png"
});
}
}
}
lastUnread=d.unread_count;
});
}
if(window.Notification&&Notification.permission==="default"){
Notification.requestPermission();
}
fetchNotif();
setInterval(fetchNotif,5000);
});