class NotificationController {
  constructor() {
    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
    this.audioElement = document.createElement('audio');
    this.audioElement.src = 'warn.mp3';
    this.isAudioInitialized = false;
    this.track = null;
    this.checkAudioPermission();
  }

  checkAudioPermission() {
    const audioPermission = getCookie('audioPermission');
    if (audioPermission === 'granted') {
      // Ses çalma izni varsa, kullanıcı etkileşimi olmadan başlatma denemesi
      this.audioContext.resume().then(() => {
        console.log('AudioContext zaten izinli.');
        this.isAudioInitialized = true;
      }).catch(error => console.error('AudioContext başlatılırken hata:', error));
      // İzin verildiğinde butonu gizle
      const audioSection = document.getElementById('audioSection');
      if (audioSection) {
        audioSection.style.display = 'none';
      }
    } else {
      // İzin yoksa, buton ile kullanıcıdan izin alınacak
      document.getElementById('audioPermissionButton').addEventListener('click', () => this.initializeAudio());
    }
  }

  initializeAudio() {
    if (!this.isAudioInitialized) {
      this.audioContext.resume().then(() => {
        console.log('AudioContext başlatıldı ve kullanıcı izni alındı.');
        this.isAudioInitialized = true;
        // İzin alındığında butonu gizle
        const audioSection = document.getElementById('audioSection');
        if (audioSection) {
          audioSection.style.display = 'none';
        }
        // İzin verildiği cookie ile kaydedilir
        setCookie('audioPermission', 'granted', 365);
      }).catch(error => console.error('AudioContext başlatılırken hata:', error));
    }
  }

  playSound() {
    if (this.audioContext.state === 'suspended') {
      this.audioContext.resume().then(() => {
        this._playSound();
      });
    } else {
      this._playSound();
    }
  }

  _playSound() {
    if (!this.track) {
      this.track = this.audioContext.createMediaElementSource(this.audioElement);
      this.track.connect(this.audioContext.destination);
    }
    this.audioElement.play().then(() => {
      console.log('Ses çalınıyor...');
    }).catch((error) => {
      console.error('Ses çalınırken bir hata oluştu:', error);
    });
  }
}

function getCookie(name) {
  let nameEQ = name + "=";
  let ca = document.cookie.split(';');
  for(let i=0;i < ca.length;i++) {
    let c = ca[i];
    while (c.charAt(0)==' ') c = c.substring(1,c.length);
    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length,c.length);
  }
  return null;
}

function setCookie(name, value, days) {
  var expires = "";
  if (days) {
    var date = new Date();
    date.setTime(date.getTime() + (days*24*60*60*1000));
    expires = "; expires=" + date.toUTCString();
  }
  document.cookie = name + "=" + (value || "") + expires + "; path=/";
}

const notificationController = new NotificationController();

function onNewMessage() {
  notificationController.playSound();
}
