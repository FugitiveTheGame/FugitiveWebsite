/*
	Games-per-day chart.
	Palette validated for CVD separation and contrast against the #1b2a4a surface.
*/
var CHART_ALL = '#ab8f00';
var CHART_BIG = '#4a97cc';
var CHART_INK = 'rgba(237, 234, 227, 0.62)';
var CHART_GRID = 'rgba(237, 234, 227, 0.10)';

function prepareGamesPerDay(labels, allGames, bigGames) {
	var canvas = document.getElementById('game-chart');
	if (!canvas) return;

	Chart.defaults.global.defaultFontFamily = '"Overpass", Helvetica, Arial, sans-serif';
	Chart.defaults.global.defaultFontColor = CHART_INK;

	new Chart(canvas.getContext('2d'), {
		type: 'line',
		data: {
			labels: labels,
			datasets: [
				{
					label: 'All games',
					borderColor: CHART_ALL,
					fill: false,
					pointBackgroundColor: CHART_ALL,
					pointBorderColor: '#1b2a4a',
					pointBorderWidth: 2,
					pointRadius: 0,
					pointHoverRadius: 5,
					borderWidth: 2,
					lineTension: 0.15,
					data: allGames
				},
				{
					label: 'Three or more players',
					borderColor: CHART_BIG,
					fill: false,
					pointBackgroundColor: CHART_BIG,
					pointBorderColor: '#1b2a4a',
					pointBorderWidth: 2,
					pointRadius: 0,
					pointHoverRadius: 5,
					borderWidth: 2,
					lineTension: 0.15,
					data: bigGames
				}
			]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			legend: { display: false },
			hover: { mode: 'index', intersect: false },
			tooltips: {
				mode: 'index',
				intersect: false,
				backgroundColor: '#14161c',
				borderColor: 'rgba(237, 234, 227, 0.16)',
				borderWidth: 1,
				titleFontFamily: '"Overpass Mono", monospace',
				bodyFontFamily: '"Overpass", Helvetica, Arial, sans-serif',
				cornerRadius: 0,
				xPadding: 12,
				yPadding: 10
			},
			scales: {
				xAxes: [{
					gridLines: { color: 'transparent', zeroLineColor: CHART_GRID },
					ticks: { fontColor: CHART_INK, maxTicksLimit: 8, autoSkip: true, maxRotation: 0, minRotation: 0, padding: 8 }
				}],
				yAxes: [{
					gridLines: { color: CHART_GRID, zeroLineColor: CHART_GRID, drawBorder: false },
					ticks: { fontColor: CHART_INK, beginAtZero: true, precision: 0 }
				}]
			}
		}
	});
}
