function prepareGamesPerDay(gpdLabels, gpdData) {
    let ctx = document.getElementById('game-chart').getContext('2d');
    let chart = new Chart(ctx, {
        // The type of chart we want to create
        type: 'line',

        // The data for our dataset
        data: {
            responsive: true,
            labels: gpdLabels,
            datasets: [{
                label: 'Games Per Day',
                backgroundColor: 'rgb(32, 32, 132)',
                borderColor: 'rgb(64, 64, 255)',
                data: gpdData
            }]
        },
        // Configuration options go here
        options: {
            maintainAspectRatio: false,
            legend: {
                display: true,
                labels: {
                    fontColor: 'rgb(255, 255, 255)'
                }
            },
            scales: {
                xAxes: [{
                    ticks: {
                        fontColor: 'white'
                    }
                }],
                yAxes: [{
                    ticks: {
                        fontColor: 'white'
                    }
                }]
            }
        }
    });
}

function prepareWinChart(winLabels, winData) {
    let ctx = document.getElementById('win-chart').getContext('2d');

    let data = {
        datasets: [{
            data: winData,
            backgroundColor: [
                'rgb(0,0,255)',
                'rgb(255,132,0)'
            ],
        }],

        // These labels appear in the legend and in the tooltips when hovering different arcs
        labels: winLabels
    };

    let winChart = new Chart(ctx, {
        type: 'pie',
        data: data,
        options: {
            responsive: true
        }
    });
}

Chart.defaults.global.defaultColor = 'rgb(255, 255, 255)';
Chart.defaults.global.legend.labels.fontColor = '#ffffff';