import "../css/admin.scss";
import tippy from "tippy.js";
import "jquery-blockui";
import "./react/AppStateComponent";
import "./react/component/form/fComptet/UserComponent";
import "./react/component/form/fArticle/ArticleComponent.tsx";
import "./react/component/form/SharedListComponent.tsx";
import "./react/component/list/ListSageEntityComponent.tsx";
import "./react/component/form/resource/ResourceFilterComponent.tsx";
import { getTranslations } from "./functions/translations";
import { basePlacements } from "@popperjs/core/lib/enums";
import { TOKEN } from "./token"; // todo refacto pour utiliser davantage de React (comme par exemple toute la partie sur la gestion des filtres)

// todo intégrer: https://github.com/woocommerce/woocommerce/pull/55508
// todo refacto pour utiliser davantage de React (comme par exemple toute la partie sur la gestion des filtres)
$(() => {
  const siteUrl = $(`[data-${TOKEN}-site-url]`).attr(`data-${TOKEN}-site-url`);
  let translations: any = getTranslations();
  // region remove ${TOKEN}_message in query
  let url = new URL(location.href);
  url.searchParams.delete(`${TOKEN}_message`);
  window.history.replaceState(null, "", url);

  // endregion

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

  function getOrderIdWpnonce() {
    const blockDom = $(`[id^='woocommerce-order-${TOKEN}']`);
    const dataDom = $(blockDom).find("[data-order-data]");
    const orderId = $(dataDom).attr("data-order-id");
    const wpnonce = $(dataDom).attr("data-nonce");
    return [orderId, wpnonce];
  }

  async function synchronizeWordpressOrderWithSage(sync: boolean) {
    const blockDom = $(`[id^='woocommerce-order-${TOKEN}']`);
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
      encodeURIComponent(`/${TOKEN}/v1/orders/` + orderId + "/sync") +
      "&_wpnonce=" +
      wpnonce;
    if (!sync) {
      url =
        siteUrl +
        "/index.php?rest_route=" +
        encodeURIComponent(
          `/${TOKEN}/v1/orders/` + orderId + "/desynchronize",
        ) +
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
        encodeURIComponent(
          `/${TOKEN}/v1/orders/` + orderId + "/meta-box-order",
        ) +
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
    if (typeof attr !== "undefined") {
      otherSelect = $(thisSelect).parent().prev().find("select");
    } else {
      otherSelect = $(thisSelect).parent().next().find("select");
    }
    let optionElement = $(e.target).detach().appendTo(otherSelect);
    $(optionElement).prop("selected", false);
  });

  $(document).on("submit", `#form_settings_${TOKEN}`, function (e) {
    $(e.target)
      .find("[data-2-select-target][name] option")
      .prop("selected", true);
  });
  // endregion

  // region remove notice dismissible
  $(document).on("click", `.${TOKEN}-notice-dismiss`, function (e) {
    $(e.target).closest("div.notice").remove();
  });
  // endregion

  // region search fdocentete
  let searchFDocentete = "";
  $(`[name="${TOKEN}-fdocentete-dopiece"]`).prop("disabled", false);
  $(document).on("input", `[name="${TOKEN}-fdocentete-dopiece"]`, function (e) {
    const inputDoPiece = e.target;
    const domContainer = $(inputDoPiece).parent();
    const domResultContainer = $(domContainer)
      .parent()
      .find(`[id="${TOKEN}-fdocentete-dopiece-result"]`);
    const inputDoType = $(domContainer).find(
      `[name="${TOKEN}-fdocentete-dotype"]`,
    );
    const inputWpnonce = $(domContainer).find(
      `[name="${TOKEN}-fdocentete-wpnonce"]`,
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
          encodeURIComponent(
            `/${TOKEN}/v1/fdocentetes/` + encodeURIComponent(currentSearch),
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
                `<div class="card cursor-pointer" data-select-${TOKEN}-fdocentete-dotype="` +
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
  $(document).on(
    "click",
    `[data-select-${TOKEN}-fdocentete-dotype]`,
    function (e) {
      const divDoType = e.target;
      const domContainer = $(divDoType).closest(".notice").parent();
      const inputDoType = $(domContainer).find(
        `[name="${TOKEN}-fdocentete-dotype"]`,
      );
      const successIcon = $(domContainer).find(".dashicons-yes");
      const errorIcon = $(domContainer).find(".dashicons-no");
      const validateButton = $(domContainer).find("[data-order-fdocentete]");

      $(domContainer).find("div.notice").remove();
      $(inputDoType).val(
        $(divDoType).attr(`data-select-${TOKEN}-fdocentete-dotype`),
      );
      $(successIcon).removeClass("hidden");
      $(errorIcon).addClass("hidden");
      $(validateButton).prop("disabled", false);
    },
  );
  $(document).on("click", "[data-order-fdocentete]", async function (_) {
    const blockDom = $(`[id^='woocommerce-order-${TOKEN}']`);
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
        encodeURIComponent(`/${TOKEN}/v1/orders/` + orderId + "/fdocentete") +
        "&_wpnonce=" +
        wpnonce,
      {
        method: "POST",
        body: JSON.stringify({
          [`${TOKEN}-fdocentete-dopiece`]: $(
            `#${TOKEN}-fdocentete-dopiece`,
          ).val(),
          [`${TOKEN}-fdocentete-dotype`]: $(
            `#${TOKEN}-fdocentete-dotype`,
          ).val(),
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
        encodeURIComponent(`/${TOKEN}/v1/farticles/` + arRef + "/import") +
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

  // region link resource
  $(document.body).on("click", `a[href*="page=${TOKEN}_"]`, function (e) {
    const defaultFilters = JSON.parse(
      $(`[data-${TOKEN}-default-filters]`).attr(
        `data-${TOKEN}-default-filters`,
      ),
    );
    const url = URL.parse(
      $(e.target).attr("href"),
      $(`[data-${TOKEN}-admin-url]`).attr(`data-${TOKEN}-admin-url`),
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
