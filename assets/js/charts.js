/* ═══════════════════════════════════════════════════════════
   Giftz — Chart.js Factory
   ═══════════════════════════════════════════════════════════ */

const GiftzCharts = (() => {

  const palette = {
    accent:    '#6C63FF',
    secondary: '#FF6584',
    success:   '#10B981',
    warning:   '#F59E0B',
    info:      '#3B82F6',
    purple:    '#8B5CF6',
    border:    '#E5E7EB',
    text:      '#6B7280',
  };

  const defaultGridOptions = {
    grid: {
      color: palette.border,
      drawBorder: false,
    },
    ticks: {
      color: palette.text,
      font: { size: 11 },
    },
  };

  const defaultLegend = {
    labels: {
      color: '#1A1D2E',
      font: { size: 12, family: 'Inter, system-ui, sans-serif' },
      padding: 16,
      usePointStyle: true,
    },
  };

  // ─── Sales Trend Line Chart ───────────────────────────────
  function salesTrend(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Sales',
          data,
          borderColor: palette.accent,
          backgroundColor: 'rgba(108,99,255,.08)',
          borderWidth: 2.5,
          pointBackgroundColor: palette.accent,
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6,
          fill: true,
          tension: 0.4,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1A1D2E',
            titleColor: '#fff',
            bodyColor: 'rgba(255,255,255,.8)',
            padding: 10,
            cornerRadius: 8,
            callbacks: {
              label: ctx => ' ' + formatCurrency(ctx.parsed.y),
            }
          }
        },
        scales: {
          x: { ...defaultGridOptions, grid: { display: false, drawBorder: false } },
          y: { ...defaultGridOptions, beginAtZero: true,
            ticks: { ...defaultGridOptions.ticks, callback: v => formatCurrency(v) }
          }
        }
      }
    });
  }

  // ─── Category Doughnut ────────────────────────────────────
  function categoryDoughnut(canvasId, labels, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    const colors = [
      palette.accent, palette.secondary, palette.success,
      palette.warning, palette.info, palette.purple,
      '#EC4899', '#14B8A6', '#F97316',
    ];

    return new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: colors.slice(0, data.length),
          borderColor: '#fff',
          borderWidth: 3,
          hoverOffset: 6,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { ...defaultLegend, position: 'bottom' },
          tooltip: {
            backgroundColor: '#1A1D2E',
            titleColor: '#fff',
            bodyColor: 'rgba(255,255,255,.8)',
            padding: 10,
            cornerRadius: 8,
          }
        },
        cutout: '65%',
      }
    });
  }

  // ─── Stock Movement Bar ───────────────────────────────────
  function stockMovement(canvasId, labels, inData, outData) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Stock In',
            data: inData,
            backgroundColor: 'rgba(16,185,129,.8)',
            borderRadius: 4,
          },
          {
            label: 'Stock Out',
            data: outData,
            backgroundColor: 'rgba(239,68,68,.75)',
            borderRadius: 4,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { ...defaultLegend, position: 'top', align: 'end' },
          tooltip: {
            backgroundColor: '#1A1D2E',
            titleColor: '#fff',
            bodyColor: 'rgba(255,255,255,.8)',
            padding: 10,
            cornerRadius: 8,
          }
        },
        scales: {
          x: { ...defaultGridOptions, grid: { display: false, drawBorder: false }, stacked: false },
          y: { ...defaultGridOptions, beginAtZero: true, stacked: false }
        }
      }
    });
  }

  // ─── Profit Bar Chart ─────────────────────────────────────
  function profitBar(canvasId, labels, revenueData, cogsData, profitData, expensesData, netProfitData) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    const datasets = [
      {
        label: 'Revenue',
        data: revenueData,
        backgroundColor: 'rgba(108,99,255,.8)',
        borderRadius: 4,
      },
      {
        label: 'COGS',
        data: cogsData,
        backgroundColor: 'rgba(245,158,11,.7)',
        borderRadius: 4,
      },
      {
        label: 'Gross Profit',
        data: profitData,
        backgroundColor: 'rgba(16,185,129,.8)',
        borderRadius: 4,
      },
    ];

    if (expensesData) {
      datasets.push({
        label: 'Expenses',
        data: expensesData,
        backgroundColor: 'rgba(239,68,68,.75)',
        borderRadius: 4,
      });
    }

    if (netProfitData) {
      datasets.push({
        label: 'Net Profit',
        data: netProfitData,
        backgroundColor: 'rgba(20,184,166,.85)',
        borderRadius: 4,
      });
    }

    return new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { ...defaultLegend, position: 'top', align: 'end' },
          tooltip: {
            backgroundColor: '#1A1D2E',
            titleColor: '#fff',
            bodyColor: 'rgba(255,255,255,.8)',
            padding: 10,
            cornerRadius: 8,
            callbacks: {
              label: ctx => ' ' + ctx.dataset.label + ': ' + formatCurrency(ctx.parsed.y),
            }
          }
        },
        scales: {
          x: { ...defaultGridOptions, grid: { display: false, drawBorder: false } },
          y: { ...defaultGridOptions, beginAtZero: true,
            ticks: { ...defaultGridOptions.ticks, callback: v => formatCurrency(v) }
          }
        }
      }
    });
  }

  // ─── Inventory Snapshot Horizontal Bar ───────────────────
  function inventorySnapshot(canvasId, labels, data, maxData) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    const colors = data.map((v, i) => v <= (maxData[i] || 5) ? 'rgba(239,68,68,.8)' : 'rgba(108,99,255,.8)');

    return new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Stock Qty',
          data,
          backgroundColor: colors,
          borderRadius: 4,
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1A1D2E',
            titleColor: '#fff',
            bodyColor: 'rgba(255,255,255,.8)',
            padding: 10,
            cornerRadius: 8,
          }
        },
        scales: {
          x: { ...defaultGridOptions, beginAtZero: true },
          y: { ...defaultGridOptions, grid: { display: false } }
        }
      }
    });
  }

  // ─── Helper ───────────────────────────────────────────────
  function formatCurrency(amount) {
    const sym = window.CURRENCY_SYMBOL || '₱';
    return sym + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  return { salesTrend, categoryDoughnut, stockMovement, profitBar, inventorySnapshot };

})();

window.GiftzCharts = GiftzCharts;
