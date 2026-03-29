(function(){
  async function cryptoRenderChart() {

    const resp = await fetch(CRYPTO_SETTINGS.endpoint);
    const data = await resp.json();

    const cryptoLast7 = data.slice(-7);
    const cryptoLabels = cryptoLast7.map(item => item.date);
    const cryptoValues = cryptoLast7.map(item => item.value);

    const cryptoDefaultRadius = 4, cryptoHoverRadius = 6;
    const cryptoRadii = cryptoValues.map((_, i) => i === cryptoValues.length - 1 ? cryptoHoverRadius : cryptoDefaultRadius);
    const cryptoBgColors = cryptoValues.map((_, i) =>
      i === cryptoValues.length - 1 ? '#33a63b' : 'rgb(255, 255, 255)'
    );
	  
	  
	const cryptoInitialHighlightPlugin = {
	  id: 'initialHighlight',
	  afterDraw(chart) {
		if (chart._initialHighlightDone) return;

		const cryptoPluginCtx = chart.ctx;
		const cryptoLastIndex = chart.data.labels.length - 1;
		if (cryptoLastIndex < 0) return;

		chart.setActiveElements([{ datasetIndex: 0, index: cryptoLastIndex }]);

		const cryptoPoint = chart.getDatasetMeta(0).data[cryptoLastIndex];
		chart.tooltip.setActiveElements(
		  [{ datasetIndex: 0, index: cryptoLastIndex }],
		  { x: cryptoPoint.x, y: cryptoPoint.y }
		);

		chart.tooltip.update(true);
		chart.tooltip.draw(cryptoPluginCtx);

		chart._initialHighlightDone = true;
	  }
	};
	  
	  
	const cryptoLastPointHighlightPlugin = {
	  id: 'lastPointHighlight',
	  afterInit(chart) {

		chart._lastPointActive = true;

		chart.canvas.addEventListener('mouseleave', () => {
		  const cryptoLastIndex = chart.data.labels.length - 1;
		  if (cryptoLastIndex < 0) return;

		  chart.setActiveElements([{ datasetIndex: 0, index: cryptoLastIndex }]);

		  const cryptoPoint = chart.getDatasetMeta(0).data[cryptoLastIndex];
		  chart.tooltip.setActiveElements(
			[{ datasetIndex: 0, index: cryptoLastIndex }],
			{ x: cryptoPoint.x, y: cryptoPoint.y }
		  );

		  chart.update({ duration: 0 });

		  chart._lastPointActive = true;
		});

		chart.canvas.addEventListener('mouseenter', () => {
		  chart._lastPointActive = false;
		});
	  },
	  afterDraw(chart) {
		if (!chart._lastPointActive) return;

		const cryptoLastIndex = chart.data.labels.length - 1;
		if (cryptoLastIndex < 0) return;

		chart.setActiveElements([{ datasetIndex: 0, index: cryptoLastIndex }]);

		const cryptoPoint = chart.getDatasetMeta(0).data[cryptoLastIndex];

		chart.tooltip.setActiveElements(
		  [{ datasetIndex: 0, index: cryptoLastIndex }],
		  { x: cryptoPoint.x, y: cryptoPoint.y }
		);

		chart.tooltip.update(true);
	  },
	  beforeEvent(chart, args) {
		if (args.event.type === 'mouseout' && chart._lastPointActive) {
		  const cryptoLastIndex = chart.data.labels.length - 1;
		  if (cryptoLastIndex < 0) return;

		  chart.setActiveElements([{ datasetIndex: 0, index: cryptoLastIndex }]);

		  const cryptoPoint = chart.getDatasetMeta(0).data[cryptoLastIndex];
		  chart.tooltip.setActiveElements(
			[{ datasetIndex: 0, index: cryptoLastIndex }],
			{ x: cryptoPoint.x, y: cryptoPoint.y }
		  );

		  chart.update({ duration: 0 });
		}
	  }
	};
	  

    const cryptoContext = document.getElementById('tokenChart').getContext('2d');
    const cryptoChartInstance = new Chart(cryptoContext, {
	  plugins: [cryptoLastPointHighlightPlugin],
      type: 'line',
      data: {
        labels: cryptoLabels,
        datasets: [{
          label: 'Token Holders',
          data: cryptoValues,
          fill: false,
          tension: 0.4,

          borderColor: '#1C142B',
          backgroundColor: '#1C142B',
          pointBackgroundColor: cryptoBgColors,
          pointBorderColor: '#1C142B',
          pointHoverBackgroundColor: '#33a63b',
          pointHoverBorderColor: '#1C142B',

          borderWidth: 2,
          pointRadius: cryptoRadii,
          pointHoverRadius: cryptoRadii
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            
            backgroundColor: '#000',
            titleColor: '#fff',
            bodyColor: '#fff',
            titleFont: { size: 0 },       
            displayColors: false,         
            callbacks: {
              title: () => '',           
              label: ctx => String(ctx.parsed.y)
            },
			padding: 10
          }
        },
        hover: {
          mode: 'nearest',
          intersect: true
        },
        scales: {
          x: {
            
            title: { display: false },
            grid: { display: false, drawBorder: false },
            
            border: { display: true, color: 'transparent' },
      			ticks: {
						    callback: function(value, index, ticks) {
						        const date = new Date(this.getLabelForValue(value));
						        const day = String(date.getDate()).padStart(2, '0');
						        const monthAbbr = date.toLocaleString('en', { month: 'short' });
						        return `${day} ${monthAbbr}`;
						    },
						    color: '#1C142B'
						}
          },
          y: {
            title: { display: false },
            beginAtZero: true,
            grid: { display: false, drawBorder: false },
            ticks: { color: 'transparent' },
            border: { display: true, color: 'transparent' }
          }
        },
      }
    });
	  
	  
  }

  document.addEventListener('DOMContentLoaded', cryptoRenderChart);
})();