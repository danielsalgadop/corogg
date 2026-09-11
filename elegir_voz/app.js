// App Descubre tu Voz - síntesis con Web Audio API, sin MP3
// Solo 3 partes del coro: Voz 1 (aguda), Voz 2 (media), Voz 3 (grave)
const VOICES = [
  {
    id:'voz1', nombre:'Voz 1', cat:'Aguda · línea clara',
    low:60, high:84, color:'#dfb76c',
    rangoTxt:'Do4 (C4) – Do6 (C6)', freqTxt:'261.6 Hz – 1046.5 Hz',
    desc:'La línea más aguda. Lleva la melodía y empieza el recorrido.',
    ejemplo:'Fichas: Repertorio → Voces → Voz 1'
  },
  {
    id:'voz2', nombre:'Voz 2', cat:'Intermedia · armonía',
    low:50, high:74, color:'#00e5ff',
    rangoTxt:'Re3 (D3) – Re5 (D5)', freqTxt:'146.8 Hz – 587.3 Hz',
    desc:'La parte intermedia que sostiene la armonía.',
    ejemplo:'Fichas: Repertorio → Voces → Voz 2'
  },
  {
    id:'voz3', nombre:'Voz 3', cat:'Grave · base',
    low:40, high:64, color:'#4ade80',
    rangoTxt:'Mi2 (E2) – Mi4 (E4)', freqTxt:'82.4 Hz – 329.6 Hz',
    desc:'El registro grave para completar el conjunto.',
    ejemplo:'Fichas: Repertorio → Voces → Voz 3'
  },
];

const NOMBRES_ES = ['Do','Do#','Re','Re#','Mi','Fa','Fa#','Sol','Sol#','La','La#','Si'];
const NOMBRES_EN = ['C','C#','D','D#','E','F','F#','G','G#','A','A#','B'];

function midiToFreq(m){ return 440 * Math.pow(2,(m-69)/12); }
function midiToName(m){
  const n = m % 12, oct = Math.floor(m/12)-1;
  return `${NOMBRES_ES[n]}${oct} (${NOMBRES_EN[n]}${oct})`;
}
function midiShort(m){
  const n = m % 12, oct = Math.floor(m/12)-1;
  return `${NOMBRES_ES[n]}${oct}`;
}

// --- Audio ---
let ctx=null;
function audio(){ if(!ctx) ctx = new (window.AudioContext||window.webkitAudioContext)(); if(ctx.state==='suspended') ctx.resume(); return ctx; }
function getVol(){ return (document.getElementById('volumen').value/100)*0.5; }
function getTimbre(){ return document.getElementById('timbre').value; }

function playTone(midi, dur=1.2){
  const ac = audio(), f = midiToFreq(midi), t = ac.currentTime;
  const vol = getVol(), timbre = getTimbre();
  const master = ac.createGain();
  master.gain.setValueAtTime(0.0001,t);
  master.gain.exponentialRampToValueAtTime(Math.max(vol,0.001), t+0.08);
  master.gain.setValueAtTime(Math.max(vol,0.001), t+dur-0.25);
  master.gain.exponentialRampToValueAtTime(0.0001, t+dur);

  if(timbre==='piano'){
    const o = ac.createOscillator(); o.type='triangle'; o.frequency.value=f;
    const o2 = ac.createOscillator(); o2.type='sine'; o2.frequency.value=f*2;
    const g2 = ac.createGain(); g2.gain.value=0.25;
    o.connect(master); o2.connect(g2); g2.connect(master);
    o.start(t); o2.start(t); o.stop(t+dur); o2.stop(t+dur);
  } else if(timbre==='flauta'){
    const o = ac.createOscillator(); o.type='sine'; o.frequency.value=f;
    const o2 = ac.createOscillator(); o2.type='sine'; o2.frequency.value=f*2;
    const g2 = ac.createGain(); g2.gain.value=0.12;
    const vib = ac.createOscillator(); vib.frequency.value=5.5;
    const vibG = ac.createGain(); vibG.gain.value=4;
    vib.connect(vibG); vibG.connect(o.frequency);
    o.connect(master); o2.connect(g2); g2.connect(master);
    o.start(t); o2.start(t); vib.start(t); o.stop(t+dur); o2.stop(t+dur); vib.stop(t+dur);
  } else { // vocal Ah
    const o1 = ac.createOscillator(); o1.type='sawtooth'; o1.frequency.value=f;
    const o2 = ac.createOscillator(); o2.type='sawtooth'; o2.frequency.value=f*1.003;
    const filt = ac.createBiquadFilter(); filt.type='lowpass'; filt.frequency.value=Math.min(f*4,3500); filt.Q.value=0.8;
    const bp = ac.createBiquadFilter(); bp.type='bandpass'; bp.frequency.value=Math.min(f*3,2500); bp.Q.value=1.2;
    const g = ac.createGain(); g.gain.value=0.5;
    o1.connect(filt); o2.connect(filt); filt.connect(bp); bp.connect(g); g.connect(master);
    o1.start(t); o2.start(t); o1.stop(t+dur); o2.stop(t+dur);
  }
  master.connect(ac.destination);
}

let scaleTimer=null;
function playScale(low, high){
  stopScale();
  let m = low, i=0;
  const step = ()=>{
    playTone(m, 0.9);
    highlightKey(m);
    showNow(m);
    m++;
    if(m<=high) scaleTimer=setTimeout(step, 650);
  };
  step();
}
function stopScale(){ if(scaleTimer){clearTimeout(scaleTimer); scaleTimer=null;} }
function playNotes(notes){
  stopScale();
  let i=0;
  const step=()=>{
    playTone(notes[i], 1.8);
    highlightKey(notes[i]);
    showNow(notes[i]);
    i++;
    if(i<notes.length) scaleTimer=setTimeout(step, 1300);
  };
  step();
}

// --- Render tarjetas ---
const grid = document.getElementById('voice-grid');
const tbody = document.getElementById('table-body');

VOICES.forEach(v=>{
  const mid = Math.round((v.low+v.high)/2);
  const el = document.createElement('div');
  el.className='card'; el.style.setProperty('--c', v.color);
  el.innerHTML=`
    <div class="cat">${v.cat}</div>
    <h3>${v.nombre}</h3>
    <div class="play-row">
      <button class="chip" data-a="low">▶ Grave</button>
      <button class="chip" data-a="mid">▶ Centro</button>
      <button class="chip" data-a="high">▶ Agudo</button>
    </div>
    <div class="play-row">
      <button class="chip three" data-a="three">▶ Oír los 3</button>
    </div>`;
  el.querySelectorAll('button').forEach(b=>{
    b.onclick=()=>{
      const a=b.dataset.a;
      if(a==='low'){stopScale();playTone(v.low);showNow(v.low);}
      if(a==='mid'){stopScale();playTone(mid);showNow(mid);}
      if(a==='high'){stopScale();playTone(v.high);showNow(v.high);}
      if(a==='three'){playNotes([v.low,mid,v.high]);}
    };
  });
  grid.appendChild(el);

  const tr=document.createElement('tr');
  tr.innerHTML=`<td><strong>${v.nombre}</strong></td><td>${v.cat.split('·')[0]}</td><td>${v.rangoTxt}</td><td>${v.freqTxt}</td><td><button class="chip" data-v="${v.id}">Escuchar</button></td>`;
  tr.querySelector('button').onclick=()=>playScale(v.low,v.high);
  tbody.appendChild(tr);
});

// --- Piano Mi2 (40) a Do6 (84) ---
const piano=document.getElementById('piano');
const nowPlaying=document.getElementById('now-playing');
const LOW_MIDI=40, HIGH_MIDI=84;
let lastPlayed=null, myLow=null, myHigh=null;
const keyEls={};

for(let m=LOW_MIDI;m<=HIGH_MIDI;m++){
  const n=m%12, isBlack=[1,3,6,8,10].includes(n);
  const k=document.createElement('div');
  k.className='key '+(isBlack?'black':'white');
  k.dataset.midi=m;
  if(!isBlack && n===0) {k.classList.add('c-note'); k.textContent=midiShort(m);}
  else if(!isBlack) k.textContent = (m===LOW_MIDI||m===HIGH_MIDI)?midiShort(m):'';
  k.title=`${midiToName(m)} · ${midiToFreq(m).toFixed(1)} Hz`;
  k.onclick=()=>{ stopScale(); playTone(m); showNow(m); highlightKey(m); lastPlayed=m; updateButtons(); };
  piano.appendChild(k);
  keyEls[m]=k;
}

function showNow(m){
  nowPlaying.innerHTML=`🎧 <strong>${midiToName(m)}</strong> · ${midiToFreq(m).toFixed(1)} Hz · MIDI ${m}<br><span class="muted small">Canta un “Ah” intentando igualarlo sin forzar</span>`;
}
function highlightKey(m){
  Object.values(keyEls).forEach(k=>k.classList.remove('active'));
  if(keyEls[m]){ keyEls[m].classList.add('active'); keyEls[m].scrollIntoView({block:'nearest',inline:'center',behavior:'smooth'}); }
}
const btnLow=document.getElementById('btn-mark-low');
const btnHigh=document.getElementById('btn-mark-high');
function updateButtons(){ btnLow.disabled = lastPlayed==null; btnHigh.disabled = lastPlayed==null; }

btnLow.onclick=()=>{ if(lastPlayed==null) return; myLow=lastPlayed; refreshMarks(); };
btnHigh.onclick=()=>{ if(lastPlayed==null) return; myHigh=lastPlayed; refreshMarks(); };
document.getElementById('btn-clear').onclick=()=>{ myLow=myHigh=lastPlayed=null; refreshMarks(); updateButtons(); document.getElementById('resultado').classList.add('hidden'); nowPlaying.textContent='Toca una tecla… 🎹'; };

function refreshMarks(){
  Object.values(keyEls).forEach(k=>k.classList.remove('mark-low','mark-high','in-range'));
  document.getElementById('low-label').textContent = myLow!=null ? `${midiToName(myLow)}` : '—';
  document.getElementById('high-label').textContent = myHigh!=null ? `${midiToName(myHigh)}` : '—';
  if(myLow!=null && myHigh!=null){
    const semis = myHigh-myLow;
    document.getElementById('range-label').textContent = semis<0 ? '⚠️ el agudo es más grave que el grave' : `${semis} semitonos`;
    if(semis>=0) for(let m=myLow;m<=myHigh;m++) keyEls[m]?.classList.add('in-range');
  } else document.getElementById('range-label').textContent='—';
  if(myLow!=null) keyEls[myLow]?.classList.add('mark-low');
  if(myHigh!=null) keyEls[myHigh]?.classList.add('mark-high');
}

// --- Análisis ---
document.getElementById('btn-analyze').onclick=()=>{
  const res=document.getElementById('resultado');
  if(myLow==null||myHigh==null){ res.classList.remove('hidden'); res.innerHTML=`<h3>⚠️ Te falta un paso</h3><p>Marca tu nota más <strong>grave</strong> y tu nota más <strong>aguda</strong> cómoda en el piano de arriba, luego vuelve a pulsar Analizar.</p>`; res.scrollIntoView({behavior:'smooth'}); return; }
  if(myHigh<myLow){ res.classList.remove('hidden'); res.innerHTML=`<h3>⚠️ Rango invertido</h3><p>Tu nota aguda (${midiToName(myHigh)}) es más grave que tu grave (${midiToName(myLow)}). Intercámbialas.</p>`; return; }
  const scored = VOICES.map(v=>{
    const lo=Math.max(myLow,v.low), hi=Math.min(myHigh,v.high);
    const overlap=Math.max(0, hi-lo+1);
    const vLen=v.high-v.low+1, uLen=myHigh-myLow+1;
    const union=vLen+uLen-overlap;
    const jacc=overlap/union;
    const centerV=(v.low+v.high)/2, centerU=(myLow+myHigh)/2;
    const dist=Math.abs(centerV-centerU);
    const score=jacc*100 - dist*1.5;
    return {...v, overlap, score, dist};
  }).sort((a,b)=>b.score-a.score);
  const best=scored[0], second=scored[1];
  const pct=Math.round(Math.max(0,Math.min(100, 55 + best.overlap*3 - best.dist*2)));
  res.classList.remove('hidden');
  res.innerHTML=`
    <h3>✨ Tu voz probable: ${best.nombre}</h3>
    <p>Tu rango cómodo: <strong>${midiToName(myLow)} – ${midiToName(myHigh)}</strong> (${myHigh-myLow} semitonos).<br>
    Coincide mejor con <strong>${best.nombre} (${best.rangoTxt})</strong> con ~${pct}% de afinidad.</p>
    <div class="bar"><i style="width:${pct}%"></i></div>
    <p><strong>2ª opción:</strong> ${second.nombre} (${second.rangoTxt})</p>
    <p class="muted small">${best.desc} ${best.ejemplo}.</p>
    <p>🎤 <strong>Consejo:</strong> ${consejo(best.id)}<br>
    <span class="muted small">Prueba a cantar la escala de ${best.nombre} arriba y comprueba si el centro te resulta cómodo. Si fuerzas los agudos, prueba ${second.nombre}.</span></p>
    <div class="play-row">
      <button class="chip scale" id="res-play">▶ Oír escala de ${best.nombre}</button>
    </div>`;
  document.getElementById('res-play').onclick=()=>playScale(best.low,best.high);
  res.scrollIntoView({behavior:'smooth'});
};

function consejo(id){
  switch(id){
    case 'voz1': return 'Tu zona es la melodía aguda. Calienta hacia arriba sin empujar y entra en las fichas de Voz 1.';
    case 'voz2': return 'Tu fuerte es el centro. Practica los pasajes de enlace y entra en las fichas de Voz 2.';
    default: return 'Tu base son los graves con apoyo y aire. No fuerces agudos y entra en las fichas de Voz 3.';
  }
}

// --- Micrófono: detección de rango en vivo, sin guardar ni subir ---
function freqToMidi(f){ return 69 + 12 * Math.log2(f / 440); }
function autoCorrelate(buf, sampleRate){
  const SIZE = buf.length;
  let rms = 0;
  for(let i=0;i<SIZE;i++) rms += buf[i]*buf[i];
  rms = Math.sqrt(rms/SIZE);
  if(rms < 0.012) return -1;
  let r1 = 0, r2 = SIZE-1;
  const th = 0.2;
  for(let i=0;i<SIZE/2;i++) if(Math.abs(buf[i])>th){ r1=i; break; }
  for(let i=1;i<SIZE/2;i++) if(Math.abs(buf[SIZE-i])>th){ r2=SIZE-i; break; }
  const b = buf.slice(r1, r2+1);
  const N = b.length;
  if(N < 64) return -1;
  const c = new Array(N).fill(0);
  for(let lag=0;lag<N;lag++) for(let i=0;i<N-lag;i++) c[lag]+=b[i]*b[i+lag];
  let d=0; while(d<N-1 && c[d]>c[d+1]) d++;
  let maxV=-1, maxP=-1;
  for(let i=d;i<N;i++) if(c[i]>maxV){ maxV=c[i]; maxP=i; }
  if(maxP<=0) return -1;
  // interpolación parabólica
  const x1=c[maxP-1]||0, x2=c[maxP], x3=c[maxP+1]||0;
  const a=(x1+x3-2*x2)/2, bb=(x3-x1)/2;
  const shift = a!==0 ? -bb/(2*a) : 0;
  const T0 = maxP + shift;
  const freq = sampleRate / T0;
  if(freq<70 || freq>1100) return -1;
  return freq;
}

let micStream=null, micAnalyser=null, micBuf=null, micRaf=null, micListening=false;
const btnMic=document.getElementById('btn-mic');

function micSetUI(){
  btnMic.textContent = micListening ? '■ Detener detección' : '🎤 Detectar con micro';
  btnMic.classList.toggle('listening', micListening);
}
function micLoop(){
  if(!micAnalyser) return;
  micAnalyser.getFloatTimeDomainData(micBuf);
  const freq = autoCorrelate(micBuf, audio().sampleRate);
  if(freq>0){
    const midi = Math.round(freqToMidi(freq));
    if(midi>=LOW_MIDI && midi<=HIGH_MIDI){
      if(myLow==null||midi<myLow) myLow=midi;
      if(myHigh==null||midi>myHigh) myHigh=midi;
      lastPlayed=midi; updateButtons(); refreshMarks(); highlightKey(midi);
      nowPlaying.innerHTML=`🎙️ <strong>${midiToName(midi)}</strong> · ${freq.toFixed(1)} Hz<br><span class="muted small">Rango: ${midiToName(myLow)} – ${midiToName(myHigh)} · pulsa Detener y luego Analizar</span>`;
    }
  }
  micRaf=requestAnimationFrame(micLoop);
}
async function micToggle(){
  if(micListening){
    if(micRaf) cancelAnimationFrame(micRaf); micRaf=null;
    micStream?.getTracks().forEach(t=>t.stop()); micStream=null; micAnalyser=null;
    micListening=false; micSetUI();
    if(myLow!=null&&myHigh!=null){
      nowPlaying.innerHTML=`🎙️ Rango por micro: <strong>${midiToName(myLow)} – ${midiToName(myHigh)}</strong><br><span class="muted small">Pulsa Analizar mi voz ✨</span>`;
    } else nowPlaying.textContent='Toca una tecla… 🎹';
    return;
  }
  if(!navigator.mediaDevices?.getUserMedia){
    nowPlaying.textContent='⚠️ Tu navegador no permite micrófono (usa localhost o HTTPS).';
    return;
  }
  try{
    nowPlaying.textContent='Pidiendo permiso de micro… 🎙️';
    micStream=await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true,autoGainControl:false}});
    const ac=audio();
    const src=ac.createMediaStreamSource(micStream);
    micAnalyser=ac.createAnalyser(); micAnalyser.fftSize=2048;
    micBuf=new Float32Array(micAnalyser.fftSize);
    src.connect(micAnalyser);
    myLow=myHigh=null; refreshMarks();
    micListening=true; micSetUI();
    nowPlaying.textContent='Canta de grave a agudo… 🎤';
    micLoop();
  }catch(e){
    nowPlaying.textContent='⚠️ Sin acceso al micro. Revisa permisos del navegador.';
  }
}
btnMic.onclick=micToggle;
