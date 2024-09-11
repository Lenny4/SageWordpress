jQuery(document).ready(function () {
  let allFilterContainer = jQuery("#filters_container");
  let siteUrl = jQuery("[data-sage-site-url]").attr('data-sage-site-url');
  let translationString = jQuery("[data-sage-translation]").attr('data-sage-translation');
  let translations = [];
  if (translationString) {
    translations = JSON.parse(translationString);
  }
  let translationJs = jQuery("[data-sage-js-translation]").attr('data-sage-js-translation');
  if (translationJs) {
    translationJs = JSON.parse(translationJs);
  }
  let apiHostUrl = jQuery("[data-sage-api-host-url]").attr('data-sage-api-host-url');
  // region remove sage_message in query
  let url = new URL(location.href);
  url.searchParams.delete('sage_message');
  window.history.replaceState(null, '', url);
  // endregion

  let index = 0;

  function getNumberFilter() {
    return jQuery(allFilterContainer).children().length;
  }

  function addFilter() {
    let allFields = JSON.parse(jQuery('[data-all-fields]').attr("data-all-fields"));

    let newFilterContainer = jQuery('<div class="filter-container" style="margin-bottom: 5px;display: flex;flex-wrap: wrap;"></div>').appendTo(allFilterContainer);

    let chooseFieldContainer = jQuery('<div></div>').appendTo(newFilterContainer);
    let chooseFieldLabel = jQuery('<label class="screen-reader-text" for="filter_field[' + index + ']">filter_field[index]</label>').appendTo(chooseFieldContainer);
    let chooseFieldSelect = jQuery('<select name="filter_field[' + index + ']" id="filter_field[' + index + ']"></select>').appendTo(chooseFieldContainer);
    let chooseFieldOptionDefault = jQuery('<option disabled selected value> -- select a field -- </option>').appendTo(chooseFieldSelect);
    for (let field of allFields) {
      let fieldName = field.name;
      if (translations[field.transDomain].hasOwnProperty(field.name)) {
        fieldName = translations[field.transDomain][field.name];
        if (typeof fieldName !== "string") {
          fieldName = fieldName.label;
        }
      }
      let chooseFieldOption = jQuery('<option value="' + field.name + '">' + fieldName + '</option>').appendTo(chooseFieldSelect);
    }

    let chooseFilterTypeContainer = jQuery('<div></div>').appendTo(newFilterContainer);
    let chooseFilterTypeLabel = jQuery('<label class="screen-reader-text" for="filter_type[' + index + ']">filter_type[index]</label>').appendTo(chooseFilterTypeContainer);
    let chooseFilterTypeSelect = jQuery('<select disabled name="filter_type[' + index + ']" id="filter_type[' + index + ']"></select>').appendTo(chooseFilterTypeContainer);
    let chooseFilterTypeOptionDefault = jQuery('<option disabled selected value></option>').appendTo(chooseFilterTypeSelect);

    let chooseValueContainer = jQuery('<div></div>').appendTo(newFilterContainer);
    let chooseValueLabel = jQuery('<label class="screen-reader-text" for="filter_value[' + index + ']">filter_value[index]</label>').appendTo(chooseValueContainer);
    let chooseValueInput = jQuery('<input disabled type="search" id="filter_value[' + index + ']" name="filter_value[' + index + ']" value="">').appendTo(chooseValueContainer);

    let deleteField = jQuery('<span data-delete-filter class="dashicons dashicons-trash button" style="padding-right: 22px"></span>').appendTo(newFilterContainer);
    index++;

    if (getNumberFilter() > 1 && jQuery('#where_condition').length !== 1) {
      let orAndSelect = jQuery('<select name="where_condition" id="where_condition"></select>').appendTo('#or_and_container');
      jQuery('<option value="or">or</option>').appendTo(orAndSelect);
      jQuery('<option value="and">and</option>').appendTo(orAndSelect);
    }

    return newFilterContainer;
  }

  function validateForm() {
    jQuery('#filter-sage').find('.error-message').remove();
    jQuery('.filter-container').each((function (index, filterContainer) {
      let chooseFieldSelect = jQuery(filterContainer).find('select[name^="filter_field["]');
      let chooseFilterTypeSelect = jQuery(filterContainer).find('select[name^="filter_type["]');
      let chooseValueInput = jQuery(filterContainer).find('input[name^="filter_value["]');

      let chooseFieldSelectVal = jQuery(chooseFieldSelect).val();
      let chooseFilterTypeSelectVal = jQuery(chooseFilterTypeSelect).val();
      let chooseValueInputVal = jQuery(chooseValueInput).val().trim();

      let chooseFieldContainer = jQuery(chooseFieldSelect).parent();
      let chooseFilterTypeContainer = jQuery(chooseFilterTypeSelect).parent();
      let chooseValueContainer = jQuery(chooseValueInput).parent();

      if (chooseFieldSelectVal == null) {
        jQuery(chooseFieldContainer).append('<p class="error-message">Please select a value</p>');
      } else if (chooseValueInputVal === '') {
        jQuery(chooseValueContainer).append('<p class="error-message">This field must not be empty</p>');
      }
    }));
    return jQuery('#filter-sage').find('.error-message').length === 0;
  }

  function removeFilter(e) {
    jQuery(e.target).closest('.filter-container').remove();
    if (getNumberFilter() <= 1) {
      jQuery('#or_and_container').html('');
    }
  }

  function onChangeField(container) {
    let allFilterType = JSON.parse(jQuery('[data-all-filter-type]').attr("data-all-filter-type"));
    let allFields = JSON.parse(jQuery('[data-all-fields]').attr("data-all-fields"));

    let field = jQuery(container).find('select[name^="filter_field"]').val();
    let chooseFilterTypeSelect = jQuery(container).find('select[name^="filter_type"]');
    let chooseValueInput = jQuery(container).find('input[name^="filter_value"]');
    jQuery(chooseFilterTypeSelect).prop("disabled", false);
    jQuery(chooseValueInput).prop("disabled", false);
    let oldOptions = [];
    jQuery(chooseFilterTypeSelect).find('option').each((function (index, option) {
      oldOptions.push(jQuery(option).val());
    }));
    const filterType = allFields.find(x => x.name === field).type;
    if (filterType === 'DateTimeOperationFilterInput') {
      jQuery(chooseValueInput).prop("type", "date");
    }
    const newOptions = allFilterType[filterType];
    if (JSON.stringify(oldOptions) !== JSON.stringify(newOptions)) {
      jQuery(chooseFilterTypeSelect).html('');
      for (let option of newOptions) {
        let typeName = option;
        if (translations.words.hasOwnProperty(option)) {
          typeName = translations.words[option];
        }
        let chooseTypeOption = jQuery('<option value="' + option + '">' + typeName + '</option>').appendTo(chooseFilterTypeSelect);
      }
    }

    showHideAvailableValues(container);
  }

  function applySelectedValue(container) {
    let chooseValueInput = jQuery(container).find('input[name^="filter_value"]');
    let chooseValueSelectInput = jQuery(container).find('select[data-filter-value-select]');
    jQuery(chooseValueInput).val(jQuery(chooseValueSelectInput).val())
  }

  function _displayOptionWithOptGroup(availableValues, dom, selectedValues) {
    for (const key in availableValues) {
      let selected = "";
      if (selectedValues.includes(key.toString())) {
        selected = 'selected="selected"'
      }
      if (typeof availableValues[key] === "string") {
        const optionDom = jQuery('<option ' + selected + ' value="' + key + '">[' + key + ']: ' + availableValues[key] + '</option>').appendTo(dom);
      } else {
        const optGroupDom = jQuery('<optgroup label="' + key + '" />').appendTo(dom);
        _displayOptionWithOptGroup(availableValues[key], optGroupDom, selectedValues)
      }
    }
  }

  function showHideAvailableValues(container) {
    let allFields = JSON.parse(jQuery('[data-all-fields]').attr("data-all-fields"));
    let chooseValueInput = jQuery(container).find('input[name^="filter_value"]');
    let field = jQuery(container).find('select[name^="filter_field"]').val();
    let chooseFilterTypeSelect = jQuery(container).find('select[name^="filter_type["]');
    let chooseFilterTypeSelectVal = jQuery(chooseFilterTypeSelect).val();

    let multiple = "";
    if (["in", "nin"].includes(chooseFilterTypeSelectVal)) {
      multiple = "multiple"
    }
    const availableValues = allFields.find(x => x.name === field).values;
    const chooseValueContainer = jQuery(chooseValueInput).parent();
    jQuery(chooseValueContainer).find('select').remove();
    if (availableValues !== null) {
      const chooseValueSelectInput = jQuery('<select data-filter-value-select ' + multiple + '></select>').appendTo(chooseValueContainer);
      // jQuery(chooseValueInput).hide(); // todo uncomment
      const selectedValues = jQuery(chooseValueInput).val().split(',');
      _displayOptionWithOptGroup(availableValues, chooseValueSelectInput, selectedValues)
      applySelectedValue(container);
    } else {
      jQuery(chooseValueInput).show();
    }
  }

  function getOrderIdWpnonce() {
    const blockDom = jQuery("[id^='woocommerce-order-sage']");
    const dataDom = jQuery(blockDom).find('[data-order-data]');
    const orderId = jQuery(dataDom).attr('data-order-id');
    const wpnonce = jQuery(dataDom).attr('data-nonce');
    return [orderId, wpnonce];
  }

  function initFiltersWithQueryParams() {
    jQuery("#all_filter_container .skeleton").remove();
    let params = Object.fromEntries((new URLSearchParams(window.location.search)).entries());
    let filters = {};
    for (let key in params) {
      if (key.startsWith("filter_")) {
        let index = key.match(/\d+/)[0];
        let select = key.match(/(?<=_)(.*?)(?=\[)/)[0];
        if (!filters.hasOwnProperty(index)) {
          filters[index] = {};
        }
        filters[index][select] = params[key];
      }
    }
    for (const [key, value] of Object.entries(filters)) {
      let newFilterContainer = addFilter();
      let chooseFieldSelect = jQuery(newFilterContainer).find('select[name^="filter_field["]');
      let chooseFilterTypeSelect = jQuery(newFilterContainer).find('select[name^="filter_type["]');
      let chooseValueInput = jQuery(newFilterContainer).find('input[name^="filter_value["]');
      jQuery(chooseFieldSelect).val(value.field);
      onChangeField(newFilterContainer);
      jQuery(chooseFilterTypeSelect).val(value.type);
      jQuery(chooseValueInput).val(value.value);
      onChangeField(newFilterContainer);
    }
    if (params.hasOwnProperty("where_condition")) {
      jQuery('#where_condition').val(params.where_condition)
    }
  }

  async function synchronizeWordpressOrderWithSage(sync) {
    const blockDom = jQuery("[id^='woocommerce-order-sage']");
    jQuery(blockDom).block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6
      }
    });
    const [orderId, wpnonce] = getOrderIdWpnonce();
    let url = siteUrl + "/index.php?rest_route=" + encodeURI("/sage/v1/orders/" + orderId + "/sync") + "&_wpnonce=" + wpnonce;
    if (!sync) {
      url = siteUrl + "/index.php?rest_route=" + encodeURI("/sage/v1/orders/" + orderId + "/desynchronize") + "&_wpnonce=" + wpnonce;
    }
    const response = await fetch(url);
    jQuery(blockDom).unblock();
    if (response.status === 200) {
      const data = await response.json();
      const blockInside = jQuery(blockDom).find(".inside");
      jQuery(blockInside).html(data.html);
    } else {
      // todo toastr
    }

    // woocommerce/assets/js/admin/meta-boxes-order.js .on( 'wc_order_items_reload', this.reload_items )
    jQuery("#woocommerce-order-items").trigger("wc_order_items_reload");
    reloadWooCommerceOrderDataBox();
  }

  async function reloadWooCommerceOrderDataBox() {
    const blockDom = jQuery("#woocommerce-order-data");
    jQuery(blockDom).block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6
      }
    });
    const [orderId, wpnonce] = getOrderIdWpnonce();
    const response = await fetch(siteUrl + "/index.php?rest_route=" + encodeURI("/sage/v1/orders/" + orderId + "/meta-box-order") + "&_wpnonce=" + wpnonce);
    jQuery(blockDom).unblock();
    if (response.status === 200) {
      const data = await response.json();
      const blockInside = jQuery(blockDom).find(".inside");
      jQuery(blockInside).html(data.html);
      jQuery(document.body).trigger('wc-enhanced-select-init');// woocommerce/assets/js/admin/wc-enhanced-select.js
    } else {
      // todo toastr
    }
  }

  // region data-2-select-target
  jQuery(document).on('click', '[data-2-select-target] option', function (e) {
    let thisSelect = jQuery(e.target).closest('select');
    let otherSelect;
    let attr = jQuery(thisSelect).attr('name');
    let sort = false;
    if (typeof attr !== 'undefined' && attr !== false) {
      sort = true;
      otherSelect = jQuery(thisSelect).parent().prev().find('select');
    } else {
      otherSelect = jQuery(thisSelect).parent().next().find('select');
    }

    let optionElement = jQuery(e.target).detach().appendTo(otherSelect)
    jQuery(optionElement).prop('selected', false);

    if (sort) {
      let listItems = otherSelect.children('option').get();
      listItems.sort(function (a, b) {
        return jQuery(a).text().toUpperCase().localeCompare(jQuery(b).text().toUpperCase());
      })
      jQuery.each(listItems, function (idx, itm) {
        otherSelect.append(itm);
      });
    }
  });

  jQuery(document).on('submit', '#form_settings_sage', function (e) {
    jQuery(e.target).find('[data-2-select-target] option').prop('selected', true);
  });
  // endregion

  // region remove notice dismissible
  jQuery(document).on('click', '.sage-notice-dismiss', function (e) {
    jQuery(e.target).closest('div.notice').remove();
  });
  // endregion

  // region filter form
  jQuery(document).on('click', '#add_filter', function (e) {
    addFilter();
  });

  jQuery(document).on('change', 'select[name^="filter_field["]', function (e) {
    onChangeField(jQuery(e.target).closest(".filter-container"));
  });

  jQuery(document).on('change', 'select[name^="filter_type["]', function (e) {
    showHideAvailableValues(jQuery(e.target).closest(".filter-container"));
  });

  jQuery(document).on('change', 'select[data-filter-value-select]', function (e) {
    applySelectedValue(jQuery(e.target).closest(".filter-container"));
  });

  jQuery(document).on('change', 'select[name="per_page"]', function (e) {
    jQuery(e.target).closest('form').submit();
  });

  jQuery(document).on('click', '[data-delete-filter]', function (e) {
    removeFilter(e);
  });

  jQuery(document).on('input', '#filter-sage *', function (e) {
    jQuery(jQuery(e.target).parent()).find('.error-message').remove();
  });

  jQuery(document).on('submit', '#filter-sage', function (e) {
    if (!validateForm()) {
      e.preventDefault();
    }
  });

  initFiltersWithQueryParams();
  // endregion

  // region websocket
  if (apiHostUrl) {
    try {
      apiHostUrl = new URL(apiHostUrl);
    } catch (_) {
      apiHostUrl = null;
    }
    if (apiHostUrl) {
      const url = 'wss://' + apiHostUrl.host + '/ws';
      console.log('start websocket', url);
      const ws = new WebSocket(url)
      ws.onopen = () => {
        console.log('ws.onopen')
        ws.send('hello world')
      }

      ws.onmessage = (message) => {
        console.log(`ws.onmessage`, message.data)
      }

      ws.onerror = (evt) => {
        console.log('ws.onerror', evt)
      }

      ws.onclose = (evt) => {
        console.log('ws.onclose', evt)
      }
    }
  }
  // endregion

  // region search fdocentete
  let searchFDocentete = "";
  jQuery(document).on('input', '[name="sage-fdocentete-dopiece"]', function (e) {
    const inputDoPiece = e.target;
    const domContainer = jQuery(inputDoPiece).parent();
    const inputDoType = jQuery(domContainer).find('[name="sage-fdocentete-dotype"]');
    const inputWpnonce = jQuery(domContainer).find('[name="sage-fdocentete-wpnonce"]');
    const successIcon = jQuery(domContainer).find(".dashicons-yes");
    const errorIcon = jQuery(domContainer).find(".dashicons-no");

    jQuery(domContainer).find("div.notice").remove();
    jQuery(successIcon).addClass("hidden");
    jQuery(errorIcon).addClass("hidden");
    jQuery(inputDoType).val('');
    searchFDocentete = inputDoPiece.value;
    const currentSearch = inputDoPiece.value;
    if (searchFDocentete.trim() === "") {
      return;
    }
    setTimeout(async () => {
      if (currentSearch !== searchFDocentete) {
        return;
      }
      const spinner = jQuery(domContainer).find(".svg-spinner");
      jQuery(spinner).removeClass("hidden");
      const response = await fetch(siteUrl + "/index.php?rest_route=" + encodeURI("/sage/v1/fdocentetes/" + encodeURIComponent(currentSearch)) + "&_wpnonce=" + jQuery(inputWpnonce).val());
      if (currentSearch !== searchFDocentete) {
        return;
      }
      jQuery(spinner).addClass("hidden");

      if (response.status === 200) {
        const fDocentetes = await response.json();
        if (fDocentetes.length === 0) {
          jQuery(errorIcon).removeClass("hidden");
        } else if (fDocentetes.length === 1) {
          jQuery(inputDoType).val(fDocentetes[0].doType);
          jQuery(successIcon).removeClass("hidden");
        } else {
          jQuery(errorIcon).removeClass("hidden");
          const multipleResultDiv = jQuery("<div class='notice notice-info'></div>").prependTo(domContainer);
          jQuery(multipleResultDiv).append('<p>' + translations.sentences.multipleDoPieces + '</p>');
          const listDom = jQuery('<div class="d-flex flex-wrap"></div>').appendTo(multipleResultDiv);
          for (const fDocentete of fDocentetes) {
            jQuery(listDom).append('<div class="card cursor-pointer" data-select-sage-fdocentete-dotype="' + fDocentete.doType + '" style="max-width: none">' +
              translations.fDocentetes.doType.values[fDocentete.doType] +
              '</div>');
          }
        }
      } else {
        jQuery(errorIcon).removeClass("hidden");
        try {
          const body = await response.json();
          const errorDiv = jQuery("<div class='notice notice-error'></div>").prependTo(domContainer);
          jQuery(errorDiv).html('<pre>' + JSON.stringify(body, undefined, 2) + '</pre>');
        } catch (e) {
          console.error(e);
        }
      }
    }, 500);
  });
  jQuery(document).on('click', '[data-select-sage-fdocentete-dotype]', function (e) {
    const divDoType = e.target;
    const domContainer = jQuery(divDoType).closest('.notice').parent();
    const inputDoType = jQuery(domContainer).find('[name="sage-fdocentete-dotype"]');
    const successIcon = jQuery(domContainer).find(".dashicons-yes");
    const errorIcon = jQuery(domContainer).find(".dashicons-no");
    jQuery(domContainer).find("div.notice").remove();
    jQuery(inputDoType).val(jQuery(divDoType).attr('data-select-sage-fdocentete-dotype'));
    jQuery(successIcon).removeClass("hidden");
    jQuery(errorIcon).addClass("hidden");
  });
  // endregion

  // region import product from an order
  jQuery(document).on('click', '[data-import-farticle]', async function (e) {
    e.stopPropagation();
    const blockDom = jQuery(e.target).closest("[id^='woocommerce-order']");
    jQuery(blockDom).block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6
      }
    });
    let target = e.target;
    if (!jQuery(target).attr('data-import-farticle')) {
      target = jQuery(target).closest('[data-import-farticle]');
    }
    const arRef = jQuery(target).attr('data-import-farticle');
    const orderId = jQuery(target).attr('data-order-id');
    const wpnonce = jQuery(target).attr('data-nonce');

    const response = await fetch(siteUrl + "/index.php?rest_route=" + encodeURI("/sage/v1/farticle/" + arRef + "/import") + "&_wpnonce=" + wpnonce + "&orderId=" + orderId);
    jQuery(blockDom).unblock();
    if (response.status === 200) {
      const data = await response.json();
      const blockInside = jQuery(target).closest(".inside");
      jQuery(blockInside).html(data.html);
    } else {
      // todo toastr
    }
  });
  // endregion

  // region de-synchronize order
  jQuery(document).on('click', '[data-synchronize-order]', async function (e) {
    e.stopPropagation();
    if (window.confirm(translationJs.synchronizeOrder)) {
      synchronizeWordpressOrderWithSage(true);
    }
  });
  jQuery(document).on('click', '[data-desynchronize-order]', async function (e) {
    e.stopPropagation();
    if (window.confirm(translationJs.desynchronizeOrder)) {
      synchronizeWordpressOrderWithSage(false);
    }
  });
  // endregion

  // region link sageEntityMenu
  jQuery(document.body).on('click', 'a[href*="page=sage_"]', function (e) {
    const defaultFilters = JSON.parse(jQuery("[data-sage-default-filters]").attr('data-sage-default-filters'));
    const url = URL.parse(jQuery(e.target).attr("href"), jQuery("[data-sage-admin-url]").attr('data-sage-admin-url'))
    let page = null;
    url.searchParams.forEach((value, key) => {
      if (key === "page") {
        page = value;
      }
    });
    if (page === null) {
      return;
    }
    for (const defaultFilter of defaultFilters) {
      if (defaultFilter.entityName === page) {
        if (defaultFilter.value) {
          for (const [k, values] of Object.entries(defaultFilter.value)) {
            for (const [i, v] of Object.entries(values)) {
              url.searchParams.append(k + "[" + i + "]", v);
            }
          }
        }
        break;
      }
    }

    jQuery(e.target).attr('href', url.href);
  });
  // endregion

  // region shipping methods: woocommerce/includes/shipping/free-shipping/class-wc-shipping-free-shipping.php:250
  function wcFreeShippingShowHideMinAmountField(el) {
    const form = jQuery(el).closest('form');

    const minAmountField = jQuery('[id^="woocommerce_"][id$="_min_amount"]', form).closest('fieldset');
    const minAmountFieldLabel = minAmountField.prev();

    const ignoreDiscountField = jQuery('[id^="woocommerce_"][id$="_ignore_discounts"]', form).closest('fieldset');
    const ignoreDiscountFieldLabel = ignoreDiscountField.prev();

    if ('coupon' === jQuery(el).val() || '' === jQuery(el).val()) {
      minAmountField.hide();
      minAmountFieldLabel.hide();

      ignoreDiscountField.hide();
      ignoreDiscountFieldLabel.hide();
    } else {
      minAmountField.show();
      minAmountFieldLabel.show();

      ignoreDiscountField.show();
      ignoreDiscountFieldLabel.show();
    }
  }

  jQuery(document.body).on('change', '[id^="woocommerce_"][id$="_requires"]', function () {
    wcFreeShippingShowHideMinAmountField(this);
  });

  jQuery(document.body).on('order-totals-recalculate-complete', function () {
    synchronizeWordpressOrderWithSage(true);
  });

  // Change while load.
  jQuery('[id^="woocommerce_"][id$="_requires"]').trigger('change');
  jQuery(document.body).on('wc_backbone_modal_loaded', function (evt, target) {
    if ('wc-modal-shipping-method-settings' === target) {
      wcFreeShippingShowHideMinAmountField(jQuery('#wc-backbone-modal-dialog [id^="woocommerce_"][id$="_requires"]', evt.currentTarget));
    }
  });
  // endregion
});
