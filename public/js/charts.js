// js/charts.js - Chart.js Visualizations for myCost

class FinancialCharts {
  constructor() {
    this.categoryChart = null;
    this.trendChart = null;
    this.categoryColors = [
      '#6366f1', '#ec4899', '#f59e0b', '#10b981', 
      '#3b82f6', '#8b5cf6', '#06b6d4', '#f97316', '#14b8a6', '#64748b'
    ];
  }

  getThemeColors() {
    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    return {
      textColor: isDark ? '#94a3b8' : '#475569',
      gridColor: isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.06)',
      tooltipBg: isDark ? 'rgba(15, 23, 42, 0.95)' : 'rgba(255, 255, 255, 0.95)',
      tooltipText: isDark ? '#f8fafc' : '#0f172a',
      borderColor: isDark ? '#1e293b' : '#e2e8f0'
    };
  }

  // Render or update Category Doughnut Chart
  renderCategoryChart(canvasId, categories) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || typeof Chart === 'undefined') return;

    const theme = this.getThemeColors();

    if (this.categoryChart) {
      this.categoryChart.destroy();
    }

    if (!categories || categories.length === 0) {
      // Empty state
      const ctx = canvas.getContext('2d');
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = theme.textColor;
      ctx.font = '14px Outfit, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('Belum ada data pengeluaran', canvas.width / 2, canvas.height / 2);
      return;
    }

    const labels = categories.map((c) => c.category);
    const dataValues = categories.map((c) => parseFloat(c.total_amount));
    const totalSum = dataValues.reduce((a, b) => a + b, 0);

    this.categoryChart = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [
          {
            data: dataValues,
            backgroundColor: this.categoryColors.slice(0, labels.length),
            borderWidth: 2,
            borderColor: theme.borderColor,
            hoverOffset: 6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: theme.textColor,
              font: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '500' },
              padding: 14,
              usePointStyle: true,
              pointStyle: 'circle'
            }
          },
          tooltip: {
            backgroundColor: theme.tooltipBg,
            titleColor: theme.tooltipText,
            bodyColor: theme.tooltipText,
            borderColor: theme.borderColor,
            borderWidth: 1,
            padding: 12,
            boxPadding: 6,
            callbacks: {
              label: (context) => {
                const val = context.raw || 0;
                const pct = totalSum > 0 ? ((val / totalSum) * 100).toFixed(1) : 0;
                return ` ${context.label}: Rp ${Number(val).toLocaleString('id-ID')} (${pct}%)`;
              }
            }
          }
        },
        animation: {
          animateRotate: true,
          duration: 900
        }
      }
    });
  }

  // Render or update Cashflow Trend Chart (Monthly Bar / Line)
  renderTrendChart(canvasId, monthlyTrend) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || typeof Chart === 'undefined') return;

    const theme = this.getThemeColors();

    if (this.trendChart) {
      this.trendChart.destroy();
    }

    if (!monthlyTrend || monthlyTrend.length === 0) {
      return;
    }

    const labels = monthlyTrend.map((m) => {
      const parts = m.month_label.split('-');
      const d = new Date(parts[0], parseInt(parts[1]) - 1, 1);
      return d.toLocaleDateString('id-ID', { month: 'short', year: '2-digit' });
    });

    const incomeData = monthlyTrend.map((m) => parseFloat(m.income));
    const expenseData = monthlyTrend.map((m) => parseFloat(m.expense));

    this.trendChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Pemasukan',
            data: incomeData,
            backgroundColor: 'rgba(16, 185, 129, 0.85)',
            borderColor: '#10b981',
            borderRadius: 6,
            borderWidth: 1,
            maxBarThickness: 28
          },
          {
            label: 'Pengeluaran',
            data: expenseData,
            backgroundColor: 'rgba(239, 68, 68, 0.85)',
            borderColor: '#ef4444',
            borderRadius: 6,
            borderWidth: 1,
            maxBarThickness: 28
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: {
            grid: { display: false },
            ticks: {
              color: theme.textColor,
              font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 }
            }
          },
          y: {
            grid: { color: theme.gridColor },
            ticks: {
              color: theme.textColor,
              font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
              callback: (val) => {
                if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
                if (val >= 1000) return (val / 1000).toFixed(0) + 'k';
                return val;
              }
            }
          }
        },
        plugins: {
          legend: {
            position: 'top',
            align: 'end',
            labels: {
              color: theme.textColor,
              font: { family: "'Plus Jakarta Sans', sans-serif", size: 12, weight: '500' },
              usePointStyle: true,
              pointStyle: 'circle'
            }
          },
          tooltip: {
            backgroundColor: theme.tooltipBg,
            titleColor: theme.tooltipText,
            bodyColor: theme.tooltipText,
            borderColor: theme.borderColor,
            borderWidth: 1,
            padding: 12,
            callbacks: {
              label: (ctx) => ` ${ctx.dataset.label}: Rp ${Number(ctx.raw).toLocaleString('id-ID')}`
            }
          }
        },
        animation: {
          duration: 900
        }
      }
    });
  }

  // Refresh chart themes on dark/light switch
  updateTheme() {
    if (this.categoryChart) {
      const theme = this.getThemeColors();
      this.categoryChart.options.plugins.legend.labels.color = theme.textColor;
      this.categoryChart.update();
    }
    if (this.trendChart) {
      const theme = this.getThemeColors();
      this.trendChart.options.plugins.legend.labels.color = theme.textColor;
      this.trendChart.options.scales.x.ticks.color = theme.textColor;
      this.trendChart.options.scales.y.ticks.color = theme.textColor;
      this.trendChart.options.scales.y.grid.color = theme.gridColor;
      this.trendChart.update();
    }
  }
}

// Global instance
const financialCharts = new FinancialCharts();
