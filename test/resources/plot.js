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
                type: 'linear',
                label: 'N',
                ticks: {
                    beginAtZero: true
                }
            }],
            yAxes: [{
                type: 'linear',
                label: 't',
                ticks: {
                    beginAtZero: true
                }
            }]
        }
    }
});
