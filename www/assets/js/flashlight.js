/* Hero flashlight: the pointer reveals the artwork, the way the game does. */
(function () {
	var banner = document.getElementById('banner');
	if (!banner) return;

	var lit = banner.querySelector('.scene-lit');
	var glow = banner.querySelector('.scene-glow');
	if (!lit) return;

	var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches
		|| !window.matchMedia('(hover: hover) and (pointer: fine)').matches;
	if (still) return;

	var pending = false;
	var x = 0;
	var y = 0;

	function paint() {
		pending = false;
		lit.style.setProperty('--fx', x + '%');
		lit.style.setProperty('--fy', y + '%');
		if (glow) {
			glow.style.setProperty('--fx', x + '%');
			glow.style.setProperty('--fy', y + '%');
		}
	}

	banner.addEventListener('pointermove', function (e) {
		var box = banner.getBoundingClientRect();
		x = ((e.clientX - box.left) / box.width) * 100;
		y = ((e.clientY - box.top) / box.height) * 100;
		if (!pending) {
			pending = true;
			window.requestAnimationFrame(paint);
		}
	});
})();
