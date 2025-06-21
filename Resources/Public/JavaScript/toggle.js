import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Icons from '@typo3/backend/icons.js';
import Notification from "@typo3/backend/notification.js";

document.querySelectorAll('.t3js-language-switch').forEach(function (link) {
  link.addEventListener('click', function (ev) {
    ev.preventDefault();

    new AjaxRequest(TYPO3.settings.ajaxUrls.invero_tree_language)
      .withQueryArguments({
        language: link.dataset.language,
      })
      .get()
      .then(async function (response) {
        const resolved = await response.resolve();

        console.log(resolved);
      });
  })
})
