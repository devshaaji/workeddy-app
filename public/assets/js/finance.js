(function (window, document) {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
      return;
    }
    fn();
  }

  function esc(value) {
    if (window.App && App.utils && App.utils.escapeHtml) {
      return App.utils.escapeHtml(value);
    }

    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function notify(type, message) {
    if (window.App && App.notify && App.notify[type]) {
      App.notify[type](message);
      return;
    }

    window.alert(message);
  }

  function request(method, url, payload) {
    return App.api.request(method, url, payload || {});
  }

  function setAlert(element, kind, message) {
    if (!element) {
      return;
    }
    element.className = 'alert alert-' + kind;
    element.textContent = message;
  }

  function money(currency, amount) {
    return esc(currency || 'USD') + ' ' + Number(amount || 0).toFixed(2);
  }

  function archiveDetail(kind) {
    document.querySelectorAll('[data-finance-archive="' + kind + '"]').forEach(function (button) {
      button.addEventListener('click', function () {
        var uuid = button.getAttribute('data-uuid');
        request('DELETE', '/api/v1/finance/' + kind + '-records/' + encodeURIComponent(uuid), {})
          .then(function (res) {
            if (!res.ok) {
              throw new Error(res.message || 'Unable to archive record.');
            }
            window.location.href = kind === 'income' ? '/finance/income' : '/finance/expenses';
          })
          ['catch'](function (error) {
            notify('error', error.message || 'Unable to archive record.');
          });
      });
    });
  }

  function initRecordForm(kind) {
    var card = document.querySelector('[data-finance-page="' + kind + '-form"]');
    if (!card) {
      return;
    }

    var form = card.querySelector('form');
    var endpoint = form.getAttribute('data-endpoint');
    var isEdit = card.getAttribute('data-mode') === 'edit';
    var alertBox = document.getElementById('finance-' + kind + '-form-alert');

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      var payload = {};
      Array.prototype.forEach.call(form.elements, function (field) {
        if (!field.name) {
          return;
        }
        payload[field.name] = field.value;
      });

      request(isEdit ? 'PUT' : 'POST', endpoint, payload)
        .then(function (res) {
          if (!res.ok) {
            throw new Error(res.message || 'Unable to save record.');
          }
          var record = res.data && (res.data[kind] || res.data);
          window.location.href = kind === 'income'
            ? '/finance/income/' + encodeURIComponent(record.uuid)
            : '/finance/expenses/' + encodeURIComponent(record.uuid);
        })
        ['catch'](function (error) {
          setAlert(alertBox, 'danger', error.message || 'Unable to save record.');
        });
    });
  }

  function initSettings() {
    var card = document.querySelector('[data-finance-page="settings"]');
    if (!card) {
      return;
    }

    var form = card.querySelector('form');
    var alertBox = document.getElementById('finance-settings-alert');
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      request('PUT', form.getAttribute('data-endpoint'), {
        default_expense_currency: form.elements.default_expense_currency.value,
        payroll_summary_enabled: form.elements.payroll_summary_enabled.checked ? 1 : 0
      }).then(function (res) {
        if (!res.ok) {
          throw new Error(res.message || 'Unable to save settings.');
        }
        setAlert(alertBox, 'success', 'Settings saved.');
      })['catch'](function (error) {
        setAlert(alertBox, 'danger', error.message || 'Unable to save settings.');
      });
    });
  }

  function initPayrollRefresh() {
    var card = document.querySelector('[data-finance-page="payroll-refresh"]');
    if (!card) {
      return;
    }

    var form = document.getElementById('finance-payroll-refresh-form');
    var alertBox = document.getElementById('finance-payroll-refresh-alert');
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      request('POST', '/api/v1/finance/payroll-summaries/refresh', {
        period_key: form.elements.period_key.value
      }).then(function (res) {
        if (!res.ok) {
          throw new Error(res.message || 'Unable to refresh payroll summary.');
        }
        setAlert(alertBox, 'success', 'Payroll summary refreshed.');
        window.setTimeout(function () { window.location.reload(); }, 400);
      })['catch'](function (error) {
        setAlert(alertBox, 'danger', error.message || 'Unable to refresh payroll summary.');
      });
    });
  }

  function initList(config) {
    var card = document.querySelector('[data-finance-page="' + config.page + '"]');
    if (!card) {
      return;
    }

    var endpoint = card.getAttribute('data-endpoint');
    var tbody = document.getElementById(config.bodyId);
    var resultCount = document.getElementById(config.resultCountId);
    var search = document.getElementById(config.searchId);
    var category = config.categoryId ? document.getElementById(config.categoryId) : null;
    var bulkBar = config.bulkBarId ? document.getElementById(config.bulkBarId) : null;
    var bulkCount = config.bulkCountId ? document.getElementById(config.bulkCountId) : null;
    var bulkArchive = config.bulkArchiveId ? document.getElementById(config.bulkArchiveId) : null;
    var selectAll = card.querySelector('.table-select-all');
    var rows = [];
    var selected = {};

    function dataset(res) {
      if (!res || !res.data) {
        return [];
      }
      return res.data[config.dataKey] || [];
    }

    function filtered() {
      var query = search && search.value ? search.value.toLowerCase() : '';
      var categoryValue = category && category.value ? category.value : '';

      return rows.filter(function (row) {
        if (categoryValue && row.category !== categoryValue) {
          return false;
        }
        if (!query) {
          return true;
        }
        var haystack = [
          row.reference_number,
          row.source_type,
          row.category,
          row.period_key,
          row.description
        ].join(' ').toLowerCase();
        return haystack.indexOf(query) !== -1;
      });
    }

    function updateBulkUi() {
      var count = Object.keys(selected).filter(function (uuid) { return selected[uuid]; }).length;
      if (bulkBar) {
        bulkBar.classList.toggle('d-none', count === 0);
      }
      if (bulkCount) {
        bulkCount.textContent = count + ' selected';
      }
      if (selectAll) {
        var visible = filtered();
        selectAll.checked = visible.length > 0 && visible.every(function (row) { return !!selected[row.uuid]; });
      }
    }

    function rowHtml(row) {
      if (config.page === 'payroll') {
        return '<tr>' +
          '<td>' + esc(row.period_key) + '</td>' +
          '<td>' + esc(row.employee_count) + '</td>' +
          '<td>' + money(row.currency, row.gross_amount) + '</td>' +
          '<td>' + money(row.currency, row.net_amount) + '</td>' +
          '<td>' + esc(row.updated_at) + '</td>' +
          '</tr>';
      }

      var detailUrl = config.page === 'income'
        ? '/finance/income/' + encodeURIComponent(row.uuid)
        : '/finance/expenses/' + encodeURIComponent(row.uuid);
      var lead = config.page === 'income' ? esc(row.source_type || '--') : esc(row.category || '--');
      return '<tr>' +
        '<td><input class="form-check-input finance-row-select" type="checkbox" data-uuid="' + esc(row.uuid) + '"' + (selected[row.uuid] ? ' checked' : '') + '></td>' +
        '<td><a href="' + detailUrl + '" class="text-reset text-decoration-none fw-medium">' + esc(row.reference_number) + '</a></td>' +
        '<td>' + lead + '</td>' +
        '<td>' + money(row.currency, row.amount) + '</td>' +
        '<td>' + esc(row.created_at) + '</td>' +
        '<td>' + esc((row.description || '').slice(0, 80)) + '</td>' +
        '<td class="text-end"><a href="' + detailUrl + '" class="btn btn-sm btn-outline-secondary">View</a></td>' +
        '</tr>';
    }

    function populateCategories() {
      if (!category) {
        return;
      }
      var values = {};
      rows.forEach(function (row) {
        if (row.category) {
          values[row.category] = true;
        }
      });
      Object.keys(values).sort().forEach(function (value) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        category.appendChild(option);
      });
    }

    function render() {
      var visible = filtered();
      if (!visible.length) {
        tbody.innerHTML = '<tr><td colspan="' + config.colspan + '" class="text-center text-secondary py-5">No records found.</td></tr>';
        if (resultCount) {
          resultCount.textContent = 'No records match the current filters.';
        }
        updateBulkUi();
        return;
      }

      tbody.innerHTML = visible.map(rowHtml).join('');
      if (resultCount) {
        resultCount.textContent = visible.length + ' record' + (visible.length === 1 ? '' : 's') + ' loaded.';
      }

      tbody.querySelectorAll('.finance-row-select').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
          selected[checkbox.getAttribute('data-uuid')] = checkbox.checked;
          updateBulkUi();
        });
      });

      updateBulkUi();
    }

    function load() {
      App.api.get(endpoint).then(function (res) {
        if (!res.ok) {
          throw new Error(res.message || 'Unable to load records.');
        }
        rows = dataset(res);
        populateCategories();
        render();
      })['catch'](function (error) {
        tbody.innerHTML = '<tr><td colspan="' + config.colspan + '" class="text-center text-danger py-5">' + esc(error.message || 'Unable to load records.') + '</td></tr>';
      });
    }

    if (search) {
      search.addEventListener('input', render);
    }
    if (category) {
      category.addEventListener('change', render);
    }
    if (selectAll) {
      selectAll.addEventListener('change', function () {
        filtered().forEach(function (row) {
          selected[row.uuid] = selectAll.checked;
        });
        render();
      });
    }
    if (bulkArchive) {
      bulkArchive.addEventListener('click', function () {
        var uuids = Object.keys(selected).filter(function (uuid) { return selected[uuid]; });
        if (!uuids.length) {
          notify('error', 'Choose at least one record to archive.');
          return;
        }
        request('POST', '/api/v1/finance/' + config.archivePath + '/bulk-archive', { uuids: uuids })
          .then(function (res) {
            if (!res.ok) {
              throw new Error(res.message || 'Unable to archive records.');
            }
            selected = {};
            load();
          })
          ['catch'](function (error) {
            notify('error', error.message || 'Unable to archive records.');
          });
      });
    }

    load();
  }

  ready(function () {
    if (!window.App || !App.api) {
      return;
    }

    initRecordForm('income');
    initRecordForm('expense');
    initSettings();
    initPayrollRefresh();
    archiveDetail('income');
    archiveDetail('expense');

    initList({
      page: 'income',
      dataKey: 'income',
      bodyId: 'finance-income-body',
      resultCountId: 'finance-income-result-count',
      searchId: 'finance-income-search',
      categoryId: 'finance-income-category',
      bulkBarId: 'finance-income-bulk-bar',
      bulkCountId: 'finance-income-selected-count',
      bulkArchiveId: 'finance-income-bulk-archive',
      archivePath: 'income-records',
      colspan: 7
    });

    initList({
      page: 'expenses',
      dataKey: 'expenses',
      bodyId: 'finance-expenses-body',
      resultCountId: 'finance-expense-result-count',
      searchId: 'finance-expense-search',
      categoryId: 'finance-expense-category',
      bulkBarId: 'finance-expense-bulk-bar',
      bulkCountId: 'finance-expense-selected-count',
      bulkArchiveId: 'finance-expense-bulk-archive',
      archivePath: 'expense-records',
      colspan: 7
    });

    initList({
      page: 'payroll',
      dataKey: 'payroll_summaries',
      bodyId: 'finance-payroll-body',
      resultCountId: 'finance-payroll-result-count',
      searchId: 'finance-payroll-search',
      archivePath: '',
      colspan: 5
    });
  });
})(window, document);
