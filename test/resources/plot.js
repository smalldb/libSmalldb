const $el = $('#plot');
const datasets = JSON.parse($el.attr('data-set'));

window.datasets = datasets;
window.chart = new Chart($el[0], {
    type: 'line',
    data: {
        datasets: datasets,
    },
    options: {
        legend: {
            display: false,
        },
        scales: {
            xAxes: [{
                id: 'xN',
                type: 'linear',
                scaleLabel: {
                    display: true,
                    labelString: 'Number of tasks',
                },
                ticks: {
                    beginAtZero: true
                }
            }],
            yAxes: [{
                id: 'yTime',
                type: 'linear',
                scaleLabel: {
                    display: false,
                    labelString: 't[s]',
                    fontColor: '#5176be',
                },
                ticks: {
                    fontColor: '#5176be',
                    callback: function(value, index, values) { return value + ' s'; },
                    beginAtZero: true
                }
            }, {
                id: 'yMem',
                type: 'linear',
	        scaleLabel: {
                    display: false,
                    labelString: 'mem[MB]',
                    fontColor: '#51be76',
                },
                ticks: {
                    fontColor: '#51be76',
                    callback: function(value, index, values) { return value + ' MB'; },
                    beginAtZero: true
                }
            }]
        }
    }
});
