import AjaxRequest from "@typo3/core/ajax/ajax-request.js";

document.querySelectorAll('.t3js-language-switch').forEach(function (link) {
  link.addEventListener('click', function (ev) {
    ev.preventDefault();

    // Remove 'active' class from all links
    document.querySelectorAll('.t3js-language-switch').forEach(function (el) {
      el.classList.remove('active');
    });

    // Add 'active' class to the clicked link
    link.classList.add('active');

    // refresh the tree only after the language has been stored, otherwise the tree may be
    // reloaded with the previous language (and the switch seems to need a second click)
    new AjaxRequest(TYPO3.settings.ajaxUrls.invero_tree_language)
      .withQueryArguments({
        language: link.dataset.language,
      })
      .get()
      .then(function () {
        top.document.dispatchEvent(new CustomEvent('typo3:pagetree:refresh'));
      });
  });
});
