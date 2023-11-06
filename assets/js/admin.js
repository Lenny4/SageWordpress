jQuery(document).ready(function () {
  var allFilterContainer = jQuery("#filters_container");
  var index = 0;

  function getNumberFilter() {
    return jQuery(allFilterContainer).children().length;
  }

  function addFilter() {
    var allFields = JSON.parse(jQuery('[data-all-fields]').attr("data-all-fields"));

    var newFilterContainer = jQuery('<div class="filter-container" style="margin-bottom: 5px"></div>').appendTo(allFilterContainer);

    var chooseFieldLabel = jQuery('<label class="screen-reader-text" for="filter_field[' + index + ']">filter_field[index]</label>').appendTo(newFilterContainer);
    var chooseFieldSelect = jQuery('<select name="filter_field[' + index + ']" id="filter_field[' + index + ']"></select>').appendTo(newFilterContainer);
    var chooseFieldOptionDefault = jQuery('<option disabled selected value> -- select a field -- </option>').appendTo(chooseFieldSelect);
    for (var field of allFields) {
      var chooseFieldOption = jQuery('<option value="' + field.name + '">field_' + field.name + '</option>').appendTo(chooseFieldSelect);
    }

    var chooseFilterTypeLabel = jQuery('<label class="screen-reader-text" for="filter_type[' + index + ']">filter_type[index]</label>').appendTo(newFilterContainer);
    var chooseFilterTypeSelect = jQuery('<select disabled name="filter_type[' + index + ']" id="filter_type[' + index + ']"></select>').appendTo(newFilterContainer);
    var chooseFilterTypeOptionDefault = jQuery('<option disabled selected value></option>').appendTo(chooseFilterTypeSelect);

    var chooseValueLabel = jQuery('<label class="screen-reader-text" for="filter_value[' + index + ']">filter_value[index]</label>').appendTo(newFilterContainer);
    var chooseValueInput = jQuery('<input disabled type="search" id="filter_value[' + index + ']" name="filter_value[' + index + ']" value="">').appendTo(newFilterContainer);

    var deleteField = jQuery('<span data-delete-filter class="dashicons dashicons-trash button" style="padding-right: 22px"></span>').appendTo(newFilterContainer);
    index++;

    if (getNumberFilter() > 1 && jQuery('#where_condition').length !== 1) {
      var orAndSelect = jQuery('<select name="where_condition" id="where_condition"></select>').appendTo('#or_and_container');
      jQuery('<option value="or">or</option>').appendTo(orAndSelect);
      jQuery('<option value="and">and</option>').appendTo(orAndSelect);
    }

    return newFilterContainer;
  }

  function removeFilter(e) {
    jQuery(e.target).closest('.filter-container').remove();
    if (getNumberFilter() <= 1) {
      jQuery('#or_and_container').html('');
    }
  }

  function onChangeField(container) {
    var allFilterType = JSON.parse(jQuery('[data-all-filter-type]').attr("data-all-filter-type"));
    var allFields = JSON.parse(jQuery('[data-all-fields]').attr("data-all-fields"));

    var field = jQuery(container).find('select[name^="filter_field"]').val();
    var chooseFilterTypeSelect = jQuery(container).find('select[name^="filter_type"]');
    var chooseValueInput = jQuery(container).find('input[name^="filter_value"]');
    jQuery(chooseFilterTypeSelect).prop("disabled", false);
    jQuery(chooseValueInput).prop("disabled", false);
    var oldOptions = [];
    jQuery(chooseFilterTypeSelect).find('option').each((function (index, option) {
      oldOptions.push(jQuery(option).val());
    }));
    const newOptions = allFilterType[allFields.find(x => x.name === field).type];
    if (JSON.stringify(oldOptions) !== JSON.stringify(newOptions)) {
      jQuery(chooseFilterTypeSelect).html('');
      for (var option of newOptions) {
        var chooseTypeOption = jQuery('<option value="' + option + '">' + option + '</option>').appendTo(chooseFilterTypeSelect);
      }
    }
  }

  function initFiltersWithQueryParams() {
    jQuery("#all_filter_container .skeleton").remove();
    var params = Object.fromEntries((new URLSearchParams(window.location.search)).entries());
    var filters = {};
    for (var key in params) {
      if (key.startsWith("filter_")) {
        var index = key.match(/\d+/)[0];
        var select = key.match(/(?<=_)(.*?)(?=\[)/)[0];
        if (!filters.hasOwnProperty(index)) {
          filters[index] = {};
        }
        filters[index][select] = params[key];
      }
    }
    for (const [key, value] of Object.entries(filters)) {
      var newFilterContainer = addFilter();
      var chooseFieldSelect = jQuery(newFilterContainer).find('select[name^="filter_field["]');
      var chooseFilterTypeSelect = jQuery(newFilterContainer).find('select[name^="filter_type["]');
      var chooseValueInput = jQuery(newFilterContainer).find('input[name^="filter_value["]');
      jQuery(chooseFieldSelect).val(value.field);
      onChangeField(newFilterContainer);
      jQuery(chooseFilterTypeSelect).val(value.type);
      jQuery(chooseValueInput).val(value.value);
    }
    if (params.hasOwnProperty("where_condition")) {
      jQuery('#where_condition').val(params.where_condition)
    }
  }

  // region data-2-select-target
  jQuery(document).on('click', '[data-2-select-target] option', function (e) {
    var thisSelect = jQuery(e.target).closest('select');
    var otherSelect;
    var attr = jQuery(thisSelect).attr('name');
    var sort = false;
    if (typeof attr !== 'undefined' && attr !== false) {
      sort = true;
      otherSelect = jQuery(thisSelect).parent().prev().find('select');
    } else {
      otherSelect = jQuery(thisSelect).parent().next().find('select');
    }

    var optionElement = jQuery(e.target).detach().appendTo(otherSelect)
    jQuery(optionElement).prop('selected', false);

    if (sort) {
      var listItems = otherSelect.children('option').get();
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

  // region filter form
  jQuery(document).on('click', '#add_filter', function (e) {
    addFilter();
  });

  jQuery(document).on('change', 'select[name^="filter_field["]', function (e) {
    onChangeField(jQuery(e.target).closest(".filter-container"));
  });

  jQuery(document).on('change', 'select[name="per_page"]', function (e) {
    jQuery(e.target).closest('form').submit();
  });

  jQuery(document).on('click', '[data-delete-filter]', function (e) {
    removeFilter(e);
  });

  initFiltersWithQueryParams();
  // endregion

});
