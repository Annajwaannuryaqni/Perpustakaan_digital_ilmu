document.addEventListener("DOMContentLoaded",()=>{
const audio=document.getElementById("notificationSound");
const toastBox=document.getElementById("toast-container");
let lastUnread=0;
let unlocked=false;
let pendingSound=false; // nandain suara yang tertunda karena diblokir browser

function unlock(){
if(unlocked||!audio)return;
audio.play().then(()=>{
audio.pause();
audio.currentTime=0;
unlocked=true;
if(pendingSound){ // kalau ada suara yang gagal diputar sebelumnya, putar sekarang
pendingSound=false;
playSound();
}
}).catch(()=>{});
}
document.addEventListener("click",unlock);
document.addEventListener("keydown",unlock);

function playSound(){
if(!audio)return;
audio.pause();
audio.currentTime=0;
audio.play().catch(()=>{
pendingSound=true; // browser blokir (belum ada interaksi) -> tunggu klik/ketik pertama
});
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

// PENTING: title & message berasal dari data yang bisa diisi pengunjung publik
// (mis. nama_lengkap/kelas dari form pendaftaran siswa/daftar.php), jadi HARUS
// di-escape dulu sebelum dipasang ke innerHTML. Kalau tidak, pengunjung bisa
// menaruh tag <script>/<img onerror=...> di nama pendaftaran, lalu kode itu
// akan ikut dieksekusi di browser admin/petugas saat notifikasi ini muncul.
function escapeHtml(str){
const div=document.createElement("div");
div.textContent=String(str==null?"":str);
return div.innerHTML;
}
const safeTitle=escapeHtml(title);
const safeMessage=escapeHtml(message);

const toast=document.createElement("div");
toast.className="toast-modern";
toast.innerHTML=`
<div class="toast-icon" style="color:${color}">
<i class="fas ${icon}"></i>
</div>
<div class="toast-body">
<div class="toast-title">${safeTitle}</div>
<div class="toast-message">${safeMessage}</div>
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
if(d.status!=="success"){
// BUG FIX: sebelumnya tetap polling tiap 5 detik walau statusnya "error"
// (mis. "Unauthorized" di halaman publik seperti siswa/daftar.php yang
// belum ada sesi login). Hentikan interval-nya supaya tidak terus-terusan
// mengirim request yang percuma.
if(pollTimer){ clearInterval(pollTimer); pollTimer=null; }
return;
}
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
let pollTimer=setInterval(fetchNotif,5000);
});