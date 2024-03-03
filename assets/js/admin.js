jQuery(document).ready(function () {
    let allFilterContainer = jQuery("#filters_container");
    let translationString = jQuery("[data-sage-translation]").attr('data-sage-translation');
    let translations = [];
    if (translationString) {
        translations = JSON.parse(translationString);
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
        const newOptions = allFilterType[allFields.find(x => x.name === field).type];
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
        }
        if (params.hasOwnProperty("where_condition")) {
            jQuery('#where_condition').val(params.where_condition)
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

    // region search fdocentete
    let searchFDocentete = "";
    jQuery(document).on('input', '[name="sage-fdocentete-dopiece"]', function (e) {
        const inputDoPiece = e.target;
        const domContainer = jQuery(inputDoPiece).parent();
        const inputDoType = jQuery(domContainer).find('[name="sage-fdocentete-dotype"]');
        const successIcon = jQuery(domContainer).find(".dashicons-yes");
        const errorIcon = jQuery(domContainer).find(".dashicons-no");

        jQuery(domContainer).find("div.notice").remove();
        jQuery(successIcon).addClass("hidden");
        jQuery(errorIcon).addClass("hidden");
        jQuery(inputDoType).val('');
        searchFDocentete = inputDoPiece.value;
        const currentSearch = inputDoPiece.value;
        setTimeout(async () => {
            if (currentSearch !== searchFDocentete) {
                return;
            }
            const spinner = jQuery(domContainer).find(".svg-spinner");
            jQuery(spinner).removeClass("hidden");
            const response = await fetch("/wp-json/sage/v1/fdocentetes/" + encodeURIComponent(currentSearch));
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
});
