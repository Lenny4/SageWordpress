/**
 * Plugin Template admin js.
 *
 *  @package WordPress Plugin Template/JS
 */

jQuery(document).ready(function () {

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
});
