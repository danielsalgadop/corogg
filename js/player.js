const pistas = {};
let enReproduccion = false;

const btnReproductor = document.getElementById('btn-reproductor');
const contenedorPistas = document.getElementById('mezclador-pistas');

if (Array.isArray(fuentesPistas)) {
  fuentesPistas.forEach(function (pistaInfo) {
    pistas[pistaInfo.id] = new Howl({
      src: [pistaInfo.url],
      loop: true,
      volume: 0.7
    });
    crearFaderInterfaz(pistaInfo);
  });
}

function crearFaderInterfaz(pistaInfo) {
  const divPista = document.createElement('div');
  divPista.className = 'pista';

  const icono = document.createElement('div');
  icono.className = 'icono-volumen';
  icono.textContent = '🔊';

  const input = document.createElement('input');
  input.type = 'range';
  input.className = 'fader-volumen';
  input.min = '0';
  input.max = '1';
  input.step = '0.01';
  input.value = '0.7';

  const info = document.createElement('div');
  info.className = 'info-pista';
  info.textContent = pistaInfo.nombre;

  const valorTxt = document.createElement('div');
  valorTxt.className = 'valor-volumen';
  valorTxt.textContent = '70%';

  input.addEventListener('input', function (e) {
    const volumen = parseFloat(e.target.value);
    pistas[pistaInfo.id].volume(volumen);
    valorTxt.textContent = Math.round(volumen * 100) + '%';
  });

  divPista.appendChild(icono);
  divPista.appendChild(input);
  divPista.appendChild(info);
  divPista.appendChild(valorTxt);
  contenedorPistas.appendChild(divPista);
}

const barraProgreso = document.getElementById('barra-progreso');
let progresoInicio = 0;
let progresoBase = 0;

function actualizarProgreso() {
  const ids = Object.keys(pistas);
  if (ids.length === 0 || !barraProgreso) return;
  const master = pistas[ids[0]];
  const dur = master.duration() || 0;
  if (dur > 0) {
    let pos = progresoBase + (performance.now() - progresoInicio) / 1000;
    if (pos > dur) pos = pos % dur;
    barraProgreso.value = (pos / dur) * 100;
    const txt = document.getElementById('progreso-texto');
    if (txt) txt.textContent = Math.round((pos / dur) * 100) + '%';
  }
  if (enReproduccion) {
    setTimeout(actualizarProgreso, 250);
  }
}

if (barraProgreso) {
  barraProgreso.addEventListener('input', function () {
    const ids = Object.keys(pistas);
    if (ids.length === 0) return;
    const master = pistas[ids[0]];
    const dur = master.duration() || 0;
    if (dur <= 0) return;
    const pos = (barraProgreso.value / 100) * dur;
    ids.forEach(function (id) {
      if (typeof pistas[id].seek === 'function') pistas[id].seek(pos);
    });
    progresoInicio = performance.now();
    progresoBase = pos;
  });
}

if (btnReproductor) {
  btnReproductor.addEventListener('click', function () {
    if (!enReproduccion) {
      btnReproductor.textContent = '⏸';
      btnReproductor.setAttribute('data-playing', '1');
      btnReproductor.style.background = '#ff007f';
      Object.keys(pistas).forEach(function (id) {
        try { pistas[id].play(); } catch (e) {}
      });
      const ids0 = Object.keys(pistas);
      const dur0 = ids0.length ? (pistas[ids0[0]].duration() || 0) : 0;
      progresoBase = dur0 > 0 ? (parseFloat(barraProgreso.value) / 100) * dur0 : 0;
      progresoInicio = performance.now();
      enReproduccion = true;
      setTimeout(actualizarProgreso, 250);
    } else {
      Object.keys(pistas).forEach(function (id) {
        try { pistas[id].pause(); } catch (e) {}
      });
      btnReproductor.textContent = '▶';
      btnReproductor.setAttribute('data-playing', '0');
      btnReproductor.style.background = '#00e5ff';
      enReproduccion = false;
    }
  });
}
