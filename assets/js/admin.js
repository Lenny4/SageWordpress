jQuery(document).ready(function () {
    var allFilterContainer = jQuery("#filters_container");
    var translationString = jQuery("[data-sage-translation]").attr('data-sage-translation');
    var translations = [];
    if (translationString) {
        translations = JSON.parse(translationString);
    }
    var apiHostUrl = jQuery("[data-sage-api-host-url]").attr('data-sage-api-host-url');
    // region remove sage_message in query
    var url = new URL(location.href);
    url.searchParams.delete('sage_message');
    window.history.replaceState(null, '', url);
    // endregion

    var index = 0;

    function getNumberFilter() {
        return jQuery(allFilterContainer).children().length;
    }

    function addFilter() {
        var allFields = JSON.parse(jQuery('[data-all-fields]').attr("data-all-fields"));

        var newFilterContainer = jQuery('<div class="filter-container" style="margin-bottom: 5px;display: flex;flex-wrap: wrap;"></div>').appendTo(allFilterContainer);

        var chooseFieldContainer = jQuery('<div></div>').appendTo(newFilterContainer);
        var chooseFieldLabel = jQuery('<label class="screen-reader-text" for="filter_field[' + index + ']">filter_field[index]</label>').appendTo(chooseFieldContainer);
        var chooseFieldSelect = jQuery('<select name="filter_field[' + index + ']" id="filter_field[' + index + ']"></select>').appendTo(chooseFieldContainer);
        var chooseFieldOptionDefault = jQuery('<option disabled selected value> -- select a field -- </option>').appendTo(chooseFieldSelect);
        for (var field of allFields) {
            let fieldName = field.name;
            if (translations[field.transDomain].hasOwnProperty(field.name)) {
                fieldName = translations[field.transDomain][field.name];
                if (typeof fieldName !== "string") {
                    fieldName = fieldName.label;
                }
            }
            var chooseFieldOption = jQuery('<option value="' + field.name + '">' + fieldName + '</option>').appendTo(chooseFieldSelect);
        }

        var chooseFilterTypeContainer = jQuery('<div></div>').appendTo(newFilterContainer);
        var chooseFilterTypeLabel = jQuery('<label class="screen-reader-text" for="filter_type[' + index + ']">filter_type[index]</label>').appendTo(chooseFilterTypeContainer);
        var chooseFilterTypeSelect = jQuery('<select disabled name="filter_type[' + index + ']" id="filter_type[' + index + ']"></select>').appendTo(chooseFilterTypeContainer);
        var chooseFilterTypeOptionDefault = jQuery('<option disabled selected value></option>').appendTo(chooseFilterTypeSelect);

        var chooseValueContainer = jQuery('<div></div>').appendTo(newFilterContainer);
        var chooseValueLabel = jQuery('<label class="screen-reader-text" for="filter_value[' + index + ']">filter_value[index]</label>').appendTo(chooseValueContainer);
        var chooseValueInput = jQuery('<input disabled type="search" id="filter_value[' + index + ']" name="filter_value[' + index + ']" value="">').appendTo(chooseValueContainer);

        var deleteField = jQuery('<span data-delete-filter class="dashicons dashicons-trash button" style="padding-right: 22px"></span>').appendTo(newFilterContainer);
        index++;

        if (getNumberFilter() > 1 && jQuery('#where_condition').length !== 1) {
            var orAndSelect = jQuery('<select name="where_condition" id="where_condition"></select>').appendTo('#or_and_container');
            jQuery('<option value="or">or</option>').appendTo(orAndSelect);
            jQuery('<option value="and">and</option>').appendTo(orAndSelect);
        }

        return newFilterContainer;
    }

    function validateForm() {
        jQuery('#filter-sage').find('.error-message').remove();
        jQuery('.filter-container').each((function (index, filterContainer) {
            var chooseFieldSelect = jQuery(filterContainer).find('select[name^="filter_field["]');
            var chooseFilterTypeSelect = jQuery(filterContainer).find('select[name^="filter_type["]');
            var chooseValueInput = jQuery(filterContainer).find('input[name^="filter_value["]');

            var chooseFieldSelectVal = jQuery(chooseFieldSelect).val();
            var chooseFilterTypeSelectVal = jQuery(chooseFilterTypeSelect).val();
            var chooseValueInputVal = jQuery(chooseValueInput).val().trim();

            var chooseFieldContainer = jQuery(chooseFieldSelect).parent();
            var chooseFilterTypeContainer = jQuery(chooseFilterTypeSelect).parent();
            var chooseValueContainer = jQuery(chooseValueInput).parent();

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
                let typeName = option;
                if (translations.words.hasOwnProperty(option)) {
                    typeName = translations.words[option];
                }
                var chooseTypeOption = jQuery('<option value="' + option + '">' + typeName + '</option>').appendTo(chooseFilterTypeSelect);
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
            console.log('start websocket', 'wss://' + apiHostUrl.host + '/Socket/ws');
            const ws = new WebSocket('wss://' + apiHostUrl.host + '/Socket/ws')
            ws.onopen = () => {
                console.log('ws opened on browser')
                ws.send('hello world')
            }

            ws.onmessage = (message) => {
                console.log(`message received`, message.data)
            }

            ws.onerror = (evt) => {
                console.log(evt)
            }

            ws.onclose = (evt) => {
                console.log(evt)
            }
        }
    }
    // endregion

});
