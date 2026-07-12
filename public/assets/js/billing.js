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

  function badge(status) {
    var map = {
      draft: 'bg-secondary-lt',
      sent: 'bg-blue-lt',
      accepted: 'bg-green-lt',
      rejected: 'bg-red-lt',
      expired: 'bg-yellow-lt',
      unpaid: 'bg-yellow-lt',
      partial: 'bg-orange-lt',
      paid: 'bg-green-lt',
      overdue: 'bg-red-lt',
      cancelled: 'bg-secondary-lt'
    };

    return '<span class="badge ' + (map[String(status).toLowerCase()] || 'bg-secondary-lt') + '">' + esc(status || '--') + '</span>';
  }

  function money(currency, amount) {
    return esc(currency || 'USD') + ' ' + Number(amount || 0).toFixed(2);
  }

  function lineItemRow() {
    return '' +
      '<div class="row g-2 align-items-end mb-2" data-billing-line-item>' +
      '<div class="col-md-6"><label class="form-label">Description</label><input class="form-control" name="description" required></div>' +
      '<div class="col-md-2"><label class="form-label">Qty</label><input class="form-control" name="quantity" type="number" min="1" step="1" value="1" required></div>' +
      '<div class="col-md-3"><label class="form-label">Unit price</label><input class="form-control" name="unit_price" type="number" min="0" step="0.01" value="0.00" required></div>' +
      '<div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100" data-billing-remove-line>&times;</button></div>' +
      '</div>';
  }

  function collectLineItems(container) {
    var items = [];
    container.querySelectorAll('[data-billing-line-item]').forEach(function (row) {
      var description = row.querySelector('[name="description"]').value.trim();
      var quantity = Number(row.querySelector('[name="quantity"]').value || 0);
      var unitPrice = Number(row.querySelector('[name="unit_price"]').value || 0);

      if (!description) {
        return;
      }

      items.push({
        description: description,
        quantity: quantity,
        unit_price: unitPrice
      });
    });

    return items;
  }

  function bindLineItems(root) {
    var container = root.querySelector('[data-billing-line-items]');
    var addButton = root.querySelector('[data-billing-add-line]');
    if (!container || !addButton) {
      return;
    }

    function ensureLine() {
      if (!container.querySelector('[data-billing-line-item]')) {
        container.insertAdjacentHTML('beforeend', lineItemRow());
      }
    }

    addButton.addEventListener('click', function () {
      container.insertAdjacentHTML('beforeend', lineItemRow());
    });

    container.addEventListener('click', function (event) {
      var button = event.target.closest('[data-billing-remove-line]');
      if (!button) {
        return;
      }

      var row = button.closest('[data-billing-line-item]');
      if (row) {
        row.remove();
      }
      ensureLine();
    });

    ensureLine();
  }

  function initForm() {
    var card = document.querySelector('[data-billing-form]');
    if (!card) {
      return;
    }

    var form = card.querySelector('form');
    var feedback = card.querySelector('[data-form-feedback]');
    bindLineItems(card);

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      var payload = {
        organization_id: Number(form.elements.organization_id.value || 0),
        currency: (form.elements.currency.value || 'USD').trim().toUpperCase(),
        items: collectLineItems(card)
      };

      if (form.elements.days_until_due) {
        payload.days_until_due = Number(form.elements.days_until_due.value || 14);
      }
      if (form.elements.days_until_expiry) {
        payload.days_until_expiry = Number(form.elements.days_until_expiry.value || 30);
      }
      if (form.elements.quotation_uuid && form.elements.quotation_uuid.value) {
        payload.quotation_uuid = form.elements.quotation_uuid.value;
      }

      if (!payload.organization_id || payload.items.length === 0) {
        notify('error', 'Select an organization and add at least one line item.');
        return;
      }

      request('POST', form.id === 'billing-invoice-form' ? '/api/v1/billing/invoices' : '/api/v1/billing/quotations', payload)
        .then(function (res) {
          if (!res.ok) {
            throw new Error(res.message || 'Unable to save record.');
          }

          if (feedback) {
            feedback.className = 'alert alert-success mb-3';
            feedback.textContent = res.message || 'Saved.';
          }

          var target = form.id === 'billing-invoice-form'
            ? '/billing/invoices/' + encodeURIComponent(res.data.uuid)
            : '/billing/quotations/' + encodeURIComponent(res.data.uuid);
          window.location.href = target;
        })
        ['catch'](function (error) {
          if (feedback) {
            feedback.className = 'alert alert-danger mb-3';
            feedback.textContent = error.message || 'Unable to save record.';
          }
          notify('error', error.message || 'Unable to save record.');
        });
    });
  }

  function initDetail() {
    var detail = document.querySelector('[data-billing-detail]');
    if (!detail) {
      return;
    }

    var type = detail.getAttribute('data-billing-detail');
    var uuid = detail.getAttribute('data-uuid');
    detail.querySelectorAll('[data-billing-detail-status]').forEach(function (button) {
      button.addEventListener('click', function () {
        var status = button.getAttribute('data-billing-detail-status');
        var endpoint;
        var method = 'PATCH';
        var payload = { status: status };

        if (type === 'quotation' && status === 'accepted') {
          endpoint = '/api/v1/billing/quotations/' + encodeURIComponent(uuid) + '/accept';
          method = 'POST';
          payload = {};
        } else {
          endpoint = '/api/v1/billing/' + (type === 'invoice' ? 'invoices' : 'quotations') + '/' + encodeURIComponent(uuid) + '/status';
        }

        request(method, endpoint, payload)
          .then(function (res) {
            if (!res.ok) {
              throw new Error(res.message || 'Unable to update record.');
            }
            window.location.reload();
          })
          ['catch'](function (error) {
            notify('error', error.message || 'Unable to update record.');
          });
      });
    });

    var archive = detail.querySelector('[data-billing-detail-archive]');
    if (archive) {
      archive.addEventListener('click', function () {
        var endpoint = '/api/v1/billing/' + (type === 'invoice' ? 'invoices' : 'quotations') + '/' + encodeURIComponent(uuid);
        request('DELETE', endpoint, {})
          .then(function (res) {
            if (!res.ok) {
              throw new Error(res.message || 'Unable to archive record.');
            }
            window.location.href = type === 'invoice' ? '/billing/invoices' : '/billing/quotations';
          })
          ['catch'](function (error) {
            notify('error', error.message || 'Unable to archive record.');
          });
      });
    }
  }

  function initList(screen) {
    var card = document.querySelector('[data-billing-screen="' + screen + '"]');
    if (!card) {
      return;
    }

    var isInvoice = screen === 'invoices';
    var endpoint = '/api/v1/billing/' + (isInvoice ? 'invoices' : 'quotations');
    var tbody = card.querySelector(isInvoice ? '#billing-invoice-body' : '#billing-quotation-body');
    var resultCount = card.querySelector(isInvoice ? '#billing-invoice-result-count' : '#billing-quotation-result-count');
    var search = card.querySelector(isInvoice ? '#billing-invoice-search' : '#billing-quotation-search');
    var status = card.querySelector(isInvoice ? '#billing-invoice-status' : '#billing-quotation-status');
    var selectAll = card.querySelector('.table-select-all');
    var bulkBar = card.querySelector(isInvoice ? '#billing-invoice-bulk-bar' : '#billing-quotation-bulk-bar');
    var selectedCount = card.querySelector(isInvoice ? '#billing-invoice-selected-count' : '#billing-quotation-selected-count');
    var bulkStatus = card.querySelector(isInvoice ? '#billing-invoice-bulk-status' : '#billing-quotation-bulk-status');
    var bulkApply = card.querySelector(isInvoice ? '#billing-invoice-bulk-apply' : '#billing-quotation-bulk-apply');
    var bulkArchive = card.querySelector(isInvoice ? '#billing-invoice-bulk-archive' : '#billing-quotation-bulk-archive');
    var selected = {};
    var records = [];

    function syncSummary(rows) {
      if (isInvoice) {
        var balance = 0;
        var unpaid = 0;
        var paid = 0;
        rows.forEach(function (row) {
          balance += Number(row.balance || 0);
          if (row.status === 'paid') { paid += 1; }
          if (row.status === 'unpaid' || row.status === 'partial' || row.status === 'overdue') { unpaid += 1; }
        });
        document.getElementById('billing-invoice-total').textContent = String(rows.length);
        document.getElementById('billing-invoice-unpaid').textContent = String(unpaid);
        document.getElementById('billing-invoice-paid').textContent = String(paid);
        document.getElementById('billing-invoice-balance').textContent = balance.toFixed(2);
      } else {
        var draft = 0;
        var accepted = 0;
        var value = 0;
        rows.forEach(function (row) {
          value += Number(row.total || 0);
          if (row.status === 'draft') { draft += 1; }
          if (row.status === 'accepted') { accepted += 1; }
        });
        document.getElementById('billing-quotation-total').textContent = String(rows.length);
        document.getElementById('billing-quotation-draft').textContent = String(draft);
        document.getElementById('billing-quotation-accepted').textContent = String(accepted);
        document.getElementById('billing-quotation-value').textContent = value.toFixed(2);
      }
    }

    function filtered() {
      var query = (search && search.value ? search.value : '').toLowerCase();
      var statusValue = status ? status.value : '';
      return records.filter(function (row) {
        if (statusValue && row.status !== statusValue) {
          return false;
        }

        if (!query) {
          return true;
        }

        var haystack = [
          row.invoice_number,
          row.quotation_number,
          row.organization_name,
          row.organization_id,
          row.status
        ].join(' ').toLowerCase();

        return haystack.indexOf(query) !== -1;
      });
    }

    function updateSelectionUi() {
      var count = Object.keys(selected).filter(function (uuid) { return selected[uuid]; }).length;
      if (bulkBar) {
        bulkBar.classList.toggle('d-none', count === 0);
      }
      if (selectedCount) {
        selectedCount.textContent = count + ' selected';
      }
      if (selectAll) {
        var rows = filtered();
        selectAll.checked = rows.length > 0 && rows.every(function (row) { return !!selected[row.uuid]; });
      }
    }

    function render() {
      var rows = filtered();
      syncSummary(rows);

      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="' + (isInvoice ? '8' : '7') + '" class="text-center text-secondary py-5">No records found.</td></tr>';
        if (resultCount) {
          resultCount.textContent = 'No records match the current filters.';
        }
        updateSelectionUi();
        return;
      }

      tbody.innerHTML = rows.map(function (row) {
        var identifier = isInvoice ? row.invoice_number : row.quotation_number;
        var detailUrl = isInvoice ? '/billing/invoices/' + encodeURIComponent(row.uuid) : '/billing/quotations/' + encodeURIComponent(row.uuid);
        var tail = isInvoice
          ? '<td>' + money(row.currency, row.balance || 0) + '</td><td>' + esc(row.due_date || '--') + '</td>'
          : '<td>' + esc(row.expires_at || '--') + '</td>';

        return '<tr>' +
          '<td><input class="form-check-input billing-row-select" type="checkbox" data-uuid="' + esc(row.uuid) + '"' + (selected[row.uuid] ? ' checked' : '') + '></td>' +
          '<td><a href="' + detailUrl + '" class="text-reset text-decoration-none fw-medium">' + esc(identifier || '--') + '</a></td>' +
          '<td>' + esc(row.organization_name || ('#' + row.organization_id)) + '</td>' +
          '<td>' + badge(row.status) + '</td>' +
          '<td>' + money(row.currency, row.total || 0) + '</td>' +
          tail +
          '<td class="text-end"><a href="' + detailUrl + '" class="btn btn-sm btn-outline-secondary">View</a></td>' +
          '</tr>';
      }).join('');

      if (resultCount) {
        resultCount.textContent = rows.length + ' record' + (rows.length === 1 ? '' : 's') + ' loaded.';
      }

      tbody.querySelectorAll('.billing-row-select').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
          selected[checkbox.getAttribute('data-uuid')] = checkbox.checked;
          updateSelectionUi();
        });
      });

      updateSelectionUi();
    }

    function reload() {
      App.api.get(endpoint).then(function (res) {
        if (!res.ok) {
          throw new Error(res.message || 'Unable to load records.');
        }
        records = Array.isArray(res.data) ? res.data : [];
        render();
      })['catch'](function (error) {
        tbody.innerHTML = '<tr><td colspan="' + (isInvoice ? '8' : '7') + '" class="text-center text-danger py-5">' + esc(error.message || 'Unable to load records.') + '</td></tr>';
      });
    }

    if (search) {
      search.addEventListener('input', render);
    }
    if (status) {
      status.addEventListener('change', render);
    }
    if (selectAll) {
      selectAll.addEventListener('change', function () {
        filtered().forEach(function (row) {
          selected[row.uuid] = selectAll.checked;
        });
        render();
      });
    }
    if (bulkApply) {
      bulkApply.addEventListener('click', function () {
        var uuids = Object.keys(selected).filter(function (uuid) { return selected[uuid]; });
        if (!uuids.length || !bulkStatus || !bulkStatus.value) {
          notify('error', 'Choose rows and a status first.');
          return;
        }
        request('POST', '/api/v1/billing/' + (isInvoice ? 'invoices' : 'quotations') + '/bulk-status', {
          uuids: uuids,
          status: bulkStatus.value
        }).then(function (res) {
          if (!res.ok) {
            throw new Error(res.message || 'Unable to update records.');
          }
          selected = {};
          reload();
        })['catch'](function (error) {
          notify('error', error.message || 'Unable to update records.');
        });
      });
    }
    if (bulkArchive) {
      bulkArchive.addEventListener('click', function () {
        var uuids = Object.keys(selected).filter(function (uuid) { return selected[uuid]; });
        if (!uuids.length) {
          notify('error', 'Choose at least one record to archive.');
          return;
        }
        request('POST', '/api/v1/billing/' + (isInvoice ? 'invoices' : 'quotations') + '/bulk-archive', { uuids: uuids })
          .then(function (res) {
            if (!res.ok) {
              throw new Error(res.message || 'Unable to archive records.');
            }
            selected = {};
            reload();
          })
          ['catch'](function (error) {
            notify('error', error.message || 'Unable to archive records.');
          });
      });
    }

    reload();
  }

  ready(function () {
    if (!window.App || !App.api) {
      return;
    }

    initForm();
    initDetail();
    initList('quotations');
    initList('invoices');
  });
})(window, document);
