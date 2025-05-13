import "../css/admin.scss";
import tippy from "tippy.js";
import "jquery-blockui";
import "./react/AppStateComponent";
import "./react/UserComponent";
import "./react/Article/ArticleComponent";
import { getTranslations } from "./functions/translations";
import { basePlacements } from "@popperjs/core/lib/enums"; // todo refacto pour utiliser davantage de React (comme par exemple toute la partie sur la gestion des filtres)

// todo intégrer: https://github.com/woocommerce/woocommerce/pull/55508
// todo refacto pour utiliser davantage de React (comme par exemple toute la partie sur la gestion des filtres)
$(() => {
  let allFilterContainer = $("#filters_container");
  const siteUrl = $("[data-sage-site-url]").attr("data-sage-site-url");
  let translations: any = getTranslations();
  // region remove sage_message in query
  let url = new URL(location.href);
  url.searchParams.delete("sage_message");
  window.history.replaceState(null, "", url);
  // endregion

  let index = 0;

  function getNumberFilter() {
    return $(allFilterContainer).children().length;
  }

  function applyTippy() {
    const tippyOptions = {
      interactive: true,
      allowHTML: true,
    };
    const selector = "[data-tippy-content]";
    let notSelector = "";
    for (const placement of basePlacements) {
      tippy(selector + "[data-tippy-placement='" + placement + "']", {
        ...tippyOptions,
        placement: placement,
      });
      notSelector += ":not([data-tippy-placement='" + placement + "'])";
    }
    // https://atomiks.github.io/tippyjs/v6/constructor/
    tippy(selector + notSelector, {
      ...tippyOptions,
    });
  }

  function setContentHtml(blockInside: JQuery, html: string) {
    window.dispatchEvent(new CustomEvent("wc_meta_boxes_order_items_init"));
    $(blockInside).html(html);
    applyTippy();
  }

  function addFilter() {
    let allFields = JSON.parse($("[data-all-fields]").attr("data-all-fields"));

    let newFilterContainer = $(
      '<div class="filter-container" style="margin-bottom: 5px;display: flex;flex-wrap: wrap;"></div>',
    ).appendTo(allFilterContainer);

    let chooseFieldContainer = $("<div></div>").appendTo(newFilterContainer);
    let chooseFieldLabel = $(
      '<label class="screen-reader-text" for="filter_field[' +
        index +
        ']">filter_field[index]</label>',
    ).appendTo(chooseFieldContainer);
    let chooseFieldSelect = $(
      '<select name="filter_field[' +
        index +
        ']" id="filter_field[' +
        index +
        ']"></select>',
    ).appendTo(chooseFieldContainer);
    let chooseFieldOptionDefault = $(
      "<option disabled selected value> -- select a field -- </option>",
    ).appendTo(chooseFieldSelect);
    for (let field of allFields) {
      let fieldName = field.name;
      if (translations[field.transDomain].hasOwnProperty(field.name)) {
        fieldName = translations[field.transDomain][field.name];
        if (typeof fieldName !== "string") {
          fieldName = fieldName.label;
        }
      }
      let chooseFieldOption = $(
        '<option value="' + field.name + '">' + fieldName + "</option>",
      ).appendTo(chooseFieldSelect);
    }

    let chooseFilterTypeContainer =
      $("<div></div>").appendTo(newFilterContainer);
    let chooseFilterTypeLabel = $(
      '<label class="screen-reader-text" for="filter_type[' +
        index +
        ']">filter_type[index]</label>',
    ).appendTo(chooseFilterTypeContainer);
    let chooseFilterTypeSelect = $(
      '<select disabled name="filter_type[' +
        index +
        ']" id="filter_type[' +
        index +
        ']"></select>',
    ).appendTo(chooseFilterTypeContainer);
    let chooseFilterTypeOptionDefault = $(
      "<option disabled selected value></option>",
    ).appendTo(chooseFilterTypeSelect);

    let chooseValueContainer = $("<div></div>").appendTo(newFilterContainer);
    let chooseValueLabel = $(
      '<label class="screen-reader-text" for="filter_value[' +
        index +
        ']">filter_value[index]</label>',
    ).appendTo(chooseValueContainer);
    let chooseValueInput = $(
      '<input disabled type="search" id="filter_value[' +
        index +
        ']" name="filter_value[' +
        index +
        ']" value="">',
    ).appendTo(chooseValueContainer);

    let deleteField = $(
      '<span data-delete-filter class="dashicons dashicons-trash button" style="padding-right: 22px"></span>',
    ).appendTo(newFilterContainer);
    index++;

    if (getNumberFilter() > 1 && $("#where_condition").length !== 1) {
      let orAndSelect = $(
        '<select name="where_condition" id="where_condition"></select>',
      ).appendTo("#or_and_container");
      $('<option value="or">or</option>').appendTo(orAndSelect);
      $('<option value="and">and</option>').appendTo(orAndSelect);
    }

    return newFilterContainer;
  }

  function validateForm() {
    $("#filter-sage").find(".error-message").remove();
    $(".filter-container").each(function (index, filterContainer) {
      let chooseFieldSelect = $(filterContainer).find(
        'select[name^="filter_field["]',
      );
      let chooseFilterTypeSelect = $(filterContainer).find(
        'select[name^="filter_type["]',
      );
      let chooseValueInput = $(filterContainer).find(
        'input[name^="filter_value["]',
      );

      let chooseFieldSelectVal = $(chooseFieldSelect).val();
      let chooseFilterTypeSelectVal = $(chooseFilterTypeSelect).val();
      let chooseValueInputVal = $(chooseValueInput).val().toString().trim();

      let chooseFieldContainer = $(chooseFieldSelect).parent();
      let chooseFilterTypeContainer = $(chooseFilterTypeSelect).parent();
      let chooseValueContainer = $(chooseValueInput).parent();

      if (chooseFieldSelectVal == null) {
        $(chooseFieldContainer).append(
          '<p class="error-message">Please select a value</p>',
        );
      } else if (chooseValueInputVal === "") {
        $(chooseValueContainer).append(
          '<p class="error-message">This field must not be empty</p>',
        );
      }
    });
    return $("#filter-sage").find(".error-message").length === 0;
  }

  function removeFilter(e: JQuery.ClickEvent) {
    $(e.target).closest(".filter-container").remove();
    if (getNumberFilter() <= 1) {
      $("#or_and_container").html("");
    }
  }

  function onChangeField(container: JQuery) {
    let allFilterType = JSON.parse(
      $("[data-all-filter-type]").attr("data-all-filter-type"),
    );
    let allFields = JSON.parse($("[data-all-fields]").attr("data-all-fields"));

    let field = $(container).find('select[name^="filter_field"]').val();
    let chooseFilterTypeSelect = $(container).find(
      'select[name^="filter_type"]',
    );
    let chooseValueInput = $(container).find('input[name^="filter_value"]');
    $(chooseFilterTypeSelect).prop("disabled", false);
    $(chooseValueInput).prop("disabled", false);
    let oldOptions: string[] = [];
    $(chooseFilterTypeSelect)
      .find("option")
      .each(function (index, option) {
        oldOptions.push($(option).val());
      });
    if (field) { // if we filter on a fields which is not in the list of available filter anymore
      const filterType = allFields.find((x: any) => x.name === field).type;
      if (filterType === "DateTimeOperationFilterInput") {
        $(chooseValueInput).prop("type", "date");
      }
      const newOptions = allFilterType[filterType];
      if (JSON.stringify(oldOptions) !== JSON.stringify(newOptions)) {
        $(chooseFilterTypeSelect).html("");
        for (let option of newOptions) {
          let typeName = option;
          if (translations.words.hasOwnProperty(option)) {
            typeName = translations.words[option];
          }
          let chooseTypeOption = $(
            '<option value="' + option + '">' + typeName + "</option>",
          ).appendTo(chooseFilterTypeSelect);
        }
      }
    }

    showHideAvailableValues(container);
  }

  function applySelectedValue(container: JQuery) {
    let chooseValueInput = $(container).find('input[name^="filter_value"]');
    let chooseValueSelectInput = $(container).find(
      "select[data-filter-value-select]",
    );
    $(chooseValueInput).val($(chooseValueSelectInput).val());
  }

  function _displayOptionWithOptGroup(
    availableValues: any,
    dom: JQuery,
    selectedValues: any,
  ) {
    for (const key in availableValues) {
      let selected = "";
      if (selectedValues.includes(key.toString())) {
        selected = 'selected="selected"';
      }
      if (typeof availableValues[key] === "string") {
        const optionDom = $(
          "<option " +
            selected +
            ' value="' +
            key +
            '">[' +
            key +
            "]: " +
            availableValues[key] +
            "</option>",
        ).appendTo(dom);
      } else {
        const optGroupDom: JQuery = $(
          '<optgroup label="' + key + '" />',
        ).appendTo(dom);
        _displayOptionWithOptGroup(
          availableValues[key],
          optGroupDom,
          selectedValues,
        );
      }
    }
  }

  function showHideAvailableValues(container: JQuery) {
    let allFields = JSON.parse($("[data-all-fields]").attr("data-all-fields"));
    let chooseValueInput = $(container).find('input[name^="filter_value"]');
    let field = $(container).find('select[name^="filter_field"]').val();
    let chooseFilterTypeSelect = $(container).find(
      'select[name^="filter_type["]',
    );
    let chooseFilterTypeSelectVal = $(chooseFilterTypeSelect).val();

    let multiple = "";
    if (["in", "nin"].includes(chooseFilterTypeSelectVal.toString())) {
      multiple = "multiple";
    }
    const availableValues = allFields.find((x: any) => x.name === field).values;
    const chooseValueContainer = $(chooseValueInput).parent();
    $(chooseValueContainer).find("select").remove();
    if (availableValues !== null) {
      const chooseValueSelectInput = $(
        "<select data-filter-value-select " + multiple + "></select>",
      ).appendTo(chooseValueContainer);
      $(chooseValueInput).hide();
      const selectedValues = $(chooseValueInput).val().toString().split(",");
      _displayOptionWithOptGroup(
        availableValues,
        chooseValueSelectInput,
        selectedValues,
      );
      applySelectedValue(container);
    } else {
      $(chooseValueInput).show();
    }
  }

  function getOrderIdWpnonce() {
    const blockDom = $("[id^='woocommerce-order-sage']");
    const dataDom = $(blockDom).find("[data-order-data]");
    const orderId = $(dataDom).attr("data-order-id");
    const wpnonce = $(dataDom).attr("data-nonce");
    return [orderId, wpnonce];
  }

  function initFiltersWithQueryParams() {
    $("#all_filter_container .skeleton").remove();
    let params = Object.fromEntries(
      new URLSearchParams(window.location.search).entries(),
    );
    let filters: any = {};
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
    for (const obj of Object.entries(filters)) {
      const value: any = obj[1];
      let newFilterContainer = addFilter();
      let chooseFieldSelect = $(newFilterContainer).find(
        'select[name^="filter_field["]',
      );
      let chooseFilterTypeSelect = $(newFilterContainer).find(
        'select[name^="filter_type["]',
      );
      let chooseValueInput = $(newFilterContainer).find(
        'input[name^="filter_value["]',
      );
      $(chooseFieldSelect).val(value.field);
      onChangeField(newFilterContainer);
      $(chooseFilterTypeSelect).val(value.type);
      $(chooseValueInput).val(value.value);
      onChangeField(newFilterContainer);
    }
    if (params.hasOwnProperty("where_condition")) {
      $("#where_condition").val(params.where_condition);
    }
  }

  async function synchronizeWordpressOrderWithSage(sync: boolean) {
    const blockDom = $("[id^='woocommerce-order-sage']");
    // @ts-ignore
    $(blockDom).block({
      message: null,
      overlayCSS: {
        background: "#fff",
        opacity: 0.6,
      },
    });
    const [orderId, wpnonce] = getOrderIdWpnonce();
    let url =
      siteUrl +
      "/index.php?rest_route=" +
      encodeURI("/sage/v1/orders/" + orderId + "/sync") +
      "&_wpnonce=" +
      wpnonce;
    if (!sync) {
      url =
        siteUrl +
        "/index.php?rest_route=" +
        encodeURI("/sage/v1/orders/" + orderId + "/desynchronize") +
        "&_wpnonce=" +
        wpnonce;
    }
    const response = await fetch(url);
    // @ts-ignore
    $(blockDom).unblock();
    if (response.status === 200) {
      const data = await response.json();
      const blockInside = $(blockDom).find(".inside");
      setContentHtml(blockInside, data.html);
    } else {
      // todo toastr
    }

    // woocommerce/assets/js/admin/meta-boxes-order.js .on( 'wc_order_items_reload', this.reload_items )
    $("#woocommerce-order-items").trigger("wc_order_items_reload");
    reloadWooCommerceOrderDataBox();
  }

  async function reloadWooCommerceOrderDataBox() {
    const blockDomData = $("#woocommerce-order-data");
    const blockDomItems = $("#woocommerce-order-items");
    // @ts-ignore
    $(blockDomData).block({
      message: null,
      overlayCSS: {
        background: "#fff",
        opacity: 0.6,
      },
    });
    // @ts-ignore
    $(blockDomItems).block({
      message: null,
      overlayCSS: {
        background: "#fff",
        opacity: 0.6,
      },
    });
    const [orderId, wpnonce] = getOrderIdWpnonce();
    const response = await fetch(
      siteUrl +
        "/index.php?rest_route=" +
        encodeURI("/sage/v1/orders/" + orderId + "/meta-box-order") +
        "&_wpnonce=" +
        wpnonce,
    );
    // @ts-ignore
    $(blockDomData).unblock();
    // @ts-ignore
    $(blockDomItems).unblock();
    if (response.status === 200) {
      const data = await response.json();
      setContentHtml($(blockDomData).find(".inside"), data.orderHtml);
      setContentHtml($(blockDomItems).find(".inside"), data.itemHtml);
      $(document.body).trigger("wc-enhanced-select-init"); // woocommerce/assets/js/admin/wc-enhanced-select.js
    } else {
      // todo toastr
    }
  }

  // region data-2-select-target
  $(document).on("click", "[data-2-select-target] option", function (e) {
    let thisSelect = $(e.target).closest("select");
    let otherSelect;
    let attr = $(thisSelect).attr("name");
    let sort = false;
    if (typeof attr !== "undefined") {
      const dataSort = $(thisSelect).attr("data-sort");
      sort = dataSort !== "0";
      otherSelect = $(thisSelect).parent().prev().find("select");
    } else {
      otherSelect = $(thisSelect).parent().next().find("select");
    }

    let optionElement = $(e.target).detach().appendTo(otherSelect);
    $(optionElement).prop("selected", false);

    if (sort) {
      let listItems = otherSelect.children("option").get();
      listItems.sort((a, b) => {
        return $(a)
          .text()
          .toUpperCase()
          .localeCompare($(b).text().toUpperCase());
      });
      $.each(listItems, (idx, itm) => {
        otherSelect.append(itm);
      });
    }
  });

  $(document).on("submit", "#form_settings_sage", function (e) {
    $(e.target).find("[data-2-select-target] option").prop("selected", true);
  });
  // endregion

  // region remove notice dismissible
  $(document).on("click", ".sage-notice-dismiss", function (e) {
    $(e.target).closest("div.notice").remove();
  });
  // endregion

  // region filter form
  $(document).on("click", "#add_filter", function (e) {
    addFilter();
  });

  $(document).on("change", 'select[name^="filter_field["]', function (e) {
    onChangeField($(e.target).closest(".filter-container"));
  });

  $(document).on("change", 'select[name^="filter_type["]', function (e) {
    showHideAvailableValues($(e.target).closest(".filter-container"));
  });

  $(document).on("change", "select[data-filter-value-select]", function (e) {
    applySelectedValue($(e.target).closest(".filter-container"));
  });

  $(document).on("change", 'select[name="per_page"]', function (e) {
    $(e.target).closest("form").submit();
  });

  $(document).on("click", "[data-delete-filter]", function (e) {
    removeFilter(e);
  });

  $(document).on("input", "#filter-sage *", function (e) {
    $($(e.target).parent()).find(".error-message").remove();
  });

  $(document).on("submit", "#filter-sage", function (e) {
    if (!validateForm()) {
      e.preventDefault();
    }
  });

  initFiltersWithQueryParams();
  // endregion

  // region search fdocentete
  let searchFDocentete = "";
  $('[name="sage-fdocentete-dopiece"]').prop("disabled", false);
  $(document).on("input", '[name="sage-fdocentete-dopiece"]', function (e) {
    const inputDoPiece = e.target;
    const domContainer = $(inputDoPiece).parent();
    const domResultContainer = $(domContainer)
      .parent()
      .find('[id="sage-fdocentete-dopiece-result"]');
    const inputDoType = $(domContainer).find('[name="sage-fdocentete-dotype"]');
    const inputWpnonce = $(domContainer).find(
      '[name="sage-fdocentete-wpnonce"]',
    );
    const successIcon = $(domContainer).find(".dashicons-yes");
    const errorIcon = $(domContainer).find(".dashicons-no");
    const validateButton = $(domContainer).find("[data-order-fdocentete]");

    $(domContainer).find("div.notice").remove();
    $(domResultContainer).html("");
    $(successIcon).addClass("hidden");
    $(errorIcon).addClass("hidden");
    $(validateButton).prop("disabled", true);
    $(inputDoType).val("");
    searchFDocentete = inputDoPiece.value;
    const currentSearch = inputDoPiece.value;
    if (searchFDocentete.trim() === "") {
      return;
    }
    setTimeout(async () => {
      if (currentSearch !== searchFDocentete) {
        return;
      }
      const spinner = $(domContainer).find(".svg-spinner");
      $(spinner).removeClass("hidden");
      const response = await fetch(
        siteUrl +
          "/index.php?rest_route=" +
          encodeURI(
            "/sage/v1/fdocentetes/" + encodeURIComponent(currentSearch),
          ) +
          "&_wpnonce=" +
          $(inputWpnonce).val(),
      );
      if (currentSearch !== searchFDocentete) {
        return;
      }
      $(spinner).addClass("hidden");

      if (response.status === 200) {
        const fDocentetes = await response.json();
        if (fDocentetes.length === 0) {
          $(errorIcon).removeClass("hidden");
        } else {
          const addNoticeToCard = (fDocentete: any, dom: JQuery) => {
            if (fDocentete.wordpressIds.length > 0) {
              const notice = $(
                '<div class="notice notice-warning"></div>',
              ).appendTo(dom);
              $(
                "<p>" +
                  translations.sentences.fDoceneteteAlreadyHasOrders +
                  ":</p>",
              ).appendTo(notice);
              const listOrders = $('<ul class="ul-horizontal"></ul>').appendTo(
                notice,
              );
              for (const wordpressId of fDocentete.wordpressIds) {
                $(
                  '<li class="ml-2 mr-2"><a href="' +
                    siteUrl +
                    "/wp-admin/admin.php?page=wc-orders&action=edit&id=" +
                    wordpressId +
                    '">#' +
                    wordpressId +
                    "</a></li>",
                ).appendTo(listOrders);
              }
            }
          };
          if (fDocentetes.length === 1) {
            $(inputDoType).val(fDocentetes[0].doType);
            $(successIcon).removeClass("hidden");
            $(validateButton).prop("disabled", false);
            addNoticeToCard(fDocentetes[0], domResultContainer);
          } else {
            $(errorIcon).removeClass("hidden");
            const multipleResultDiv = $(
              "<div class='notice notice-info'></div>",
            ).prependTo(domContainer);
            $(multipleResultDiv).append(
              "<p>" + translations.sentences.multipleDoPieces + "</p>",
            );
            const listDom = $('<div class="d-flex flex-wrap"></div>').appendTo(
              multipleResultDiv,
            );
            for (const fDocentete of fDocentetes) {
              let label = "";
              for (const key in translations.fDocentetes.doType.values) {
                if (
                  translations.fDocentetes.doType.values[key].hasOwnProperty(
                    fDocentete.doType,
                  )
                ) {
                  label =
                    translations.fDocentetes.doType.values[key][
                      fDocentete.doType
                    ];
                  break;
                }
              }
              const cardDoType = $(
                '<div class="card cursor-pointer" data-select-sage-fdocentete-dotype="' +
                  fDocentete.doType +
                  '" style="max-width: none">' +
                  label +
                  "</div>",
              ).appendTo(listDom);
              addNoticeToCard(fDocentete, cardDoType);
            }
          }
        }
      } else {
        $(errorIcon).removeClass("hidden");
        try {
          const body = await response.json();
          const errorDiv = $(
            "<div class='notice notice-error'></div>",
          ).prependTo(domContainer);
          $(errorDiv).html(
            "<pre>" + JSON.stringify(body, undefined, 2) + "</pre>",
          );
        } catch (e) {
          console.error(e);
        }
      }
    }, 500);
  });
  $(document).on("click", "[data-select-sage-fdocentete-dotype]", function (e) {
    const divDoType = e.target;
    const domContainer = $(divDoType).closest(".notice").parent();
    const inputDoType = $(domContainer).find('[name="sage-fdocentete-dotype"]');
    const successIcon = $(domContainer).find(".dashicons-yes");
    const errorIcon = $(domContainer).find(".dashicons-no");
    const validateButton = $(domContainer).find("[data-order-fdocentete]");

    $(domContainer).find("div.notice").remove();
    $(inputDoType).val($(divDoType).attr("data-select-sage-fdocentete-dotype"));
    $(successIcon).removeClass("hidden");
    $(errorIcon).addClass("hidden");
    $(validateButton).prop("disabled", false);
  });
  $(document).on("click", "[data-order-fdocentete]", async function (e) {
    const blockDom = $("[id^='woocommerce-order-sage']");
    // @ts-ignore
    $(blockDom).block({
      message: null,
      overlayCSS: {
        background: "#fff",
        opacity: 0.6,
      },
    });
    const [orderId, wpnonce] = getOrderIdWpnonce();
    const response = await fetch(
      siteUrl +
        "/index.php?rest_route=" +
        encodeURI("/sage/v1/orders/" + orderId + "/fdocentete") +
        "&_wpnonce=" +
        wpnonce,
      {
        method: "POST",
        body: JSON.stringify({
          ["sage-fdocentete-dopiece"]: $("#sage-fdocentete-dopiece").val(),
          ["sage-fdocentete-dotype"]: $("#sage-fdocentete-dotype").val(),
        }),
      },
    );
    // @ts-ignore
    $(blockDom).unblock();
    if (response.status === 200) {
      const data = await response.json();
      const blockInside = $(blockDom).find(".inside");
      setContentHtml(blockInside, data.html);
      $("#woocommerce-order-items").trigger("wc_order_items_reload");
      reloadWooCommerceOrderDataBox();
    } else {
      // todo toastr
    }
  });
  // endregion

  // region import product from an order
  $(document).on("click", "[data-import-farticle]", async function (e) {
    e.stopPropagation();
    const blockDom = $(e.target).closest("[id^='woocommerce-order']");
    // @ts-ignore
    $(blockDom).block({
      message: null,
      overlayCSS: {
        background: "#fff",
        opacity: 0.6,
      },
    });
    let target = e.target;
    if (!$(target).attr("data-import-farticle")) {
      target = $(target).closest("[data-import-farticle]");
    }
    const arRef = $(target).attr("data-import-farticle");
    const orderId = $(target).attr("data-order-id");
    const wpnonce = $(target).attr("data-nonce");

    const response = await fetch(
      siteUrl +
        "/index.php?rest_route=" +
        encodeURI("/sage/v1/farticle/" + arRef + "/import") +
        "&_wpnonce=" +
        wpnonce +
        "&orderId=" +
        orderId,
    );
    // @ts-ignore
    $(blockDom).unblock();
    const data = await response.json();
    const blockInside = $(target).closest(".inside");
    setContentHtml(blockInside, data.html);
  });
  // endregion

  // region de-synchronize order
  $(document).on("click", "[data-synchronize-order]", async function (e) {
    e.stopPropagation();
    if (window.confirm(translations.sentences.synchronizeOrder)) {
      synchronizeWordpressOrderWithSage(true);
    }
  });
  $(document).on("click", "[data-desynchronize-order]", async function (e) {
    e.stopPropagation();
    if (window.confirm(translations.sentences.desynchronizeOrder)) {
      synchronizeWordpressOrderWithSage(false);
    }
  });
  // endregion

  // region link sageEntityMenu
  $(document.body).on("click", 'a[href*="page=sage_"]', function (e) {
    const defaultFilters = JSON.parse(
      $("[data-sage-default-filters]").attr("data-sage-default-filters"),
    );
    const url = URL.parse(
      $(e.target).attr("href"),
      $("[data-sage-admin-url]").attr("data-sage-admin-url"),
    );
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

    $(e.target).attr("href", url.href);
  });
  // endregion

  // region shipping methods: woocommerce/includes/shipping/free-shipping/class-wc-shipping-free-shipping.php:250
  function wcFreeShippingShowHideMinAmountField(el: JQuery) {
    const form = $(el).closest("form");

    const minAmountField = $(
      '[id^="woocommerce_"][id$="_min_amount"]',
      form,
    ).closest("fieldset");
    const minAmountFieldLabel = minAmountField.prev();

    const ignoreDiscountField = $(
      '[id^="woocommerce_"][id$="_ignore_discounts"]',
      form,
    ).closest("fieldset");
    const ignoreDiscountFieldLabel = ignoreDiscountField.prev();

    if ("coupon" === $(el).val() || "" === $(el).val()) {
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

  $(document.body).on(
    "change",
    '[id^="woocommerce_"][id$="_requires"]',
    function () {
      wcFreeShippingShowHideMinAmountField(this);
    },
  );

  $(document.body).on("order-totals-recalculate-complete", function () {
    synchronizeWordpressOrderWithSage(true);
  });

  // Change while load.
  $('[id^="woocommerce_"][id$="_requires"]').trigger("change");
  $(document.body).on("wc_backbone_modal_loaded", function (evt, target) {
    if ("wc-modal-shipping-method-settings" === target) {
      wcFreeShippingShowHideMinAmountField(
        $(
          '#wc-backbone-modal-dialog [id^="woocommerce_"][id$="_requires"]',
          evt.currentTarget,
        ),
      );
    }
  });
  // endregion

  // region tooltip
  applyTippy();
  // endregion
});
