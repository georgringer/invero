/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
import DocumentService from "@typo3/core/document-service.js";
import { selector } from "@typo3/core/literals.js";
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import "@typo3/core/ajax/ajax-response.js";
import Icons from "@typo3/backend/icons.js";

class PasswordElement extends HTMLElement {
  constructor() {
    super();
    this.element = null;
    this.passwordPolicyInfo = null;
    this.passwordPolicySet = false;
  }

  async connectedCallback() {
    if (this.element !== null) {
      return;
    }

    const recordFieldId = this.getAttribute("recordFieldId");
    if (recordFieldId !== null) {
      await DocumentService.ready();
      this.element = this.querySelector(selector`#${recordFieldId}`);
      if (this.element) {
        this.passwordPolicyInfo = this.querySelector(selector`#password-policy-info-${this.element.id}`);
        this.passwordPolicySet = (this.getAttribute("passwordPolicy") || "") !== "";
        this.registerEventHandler();
      }
    }
  }

  registerEventHandler() {
    if (this.passwordPolicySet && this.passwordPolicyInfo !== null) {
      this.element.addEventListener("focusin", () => {
        this.passwordPolicyInfo.classList.remove("hidden");
      });

      this.element.addEventListener("focusout", () => {
        this.passwordPolicyInfo.classList.add("hidden");
      });

      this.element.addEventListener("input", () => {
        const listItems = this.querySelectorAll(selector`#password-policy-info-${this.element.id} li`);
        const ids = Array.from(listItems).map((li) => li.dataset.id);

        new AjaxRequest(TYPO3.settings.ajaxUrls.password_meter_verify)
          .post({
            password: this.element.value,
            list: ids
          })
          .then(async (response) => {
            const result = await response.resolve();
            if (result.success === true) {
              listItems.forEach((li) => {
                li.classList.remove("text-danger", "text-success");
                li.classList.add("text-success");
                const iconTarget = li.querySelector("span");
                Icons.getIcon("actions-check-circle", Icons.sizes.small).then((icon) => {
                  iconTarget.innerHTML = icon + '&nbsp;';
                });
              });
            } else {
              listItems.forEach((li) => {
                li.classList.remove("text-danger", "text-success");

                const iconTarget = li.querySelector("span");

                if (result.errors.includes(li.getAttribute("data-id"))) {
                  Icons.getIcon("actions-exclamation-circle", Icons.sizes.small).then((icon) => {
                    iconTarget.innerHTML = icon + '&nbsp;';
                  });
                  li.classList.add("text-danger");
                } else {
                  Icons.getIcon("actions-check-circle", Icons.sizes.small).then((icon) => {
                    iconTarget.innerHTML = icon + '&nbsp;';
                  });
                  li.classList.add("text-success");
                }
              });
            }
          })
          .catch(() => {
            // Error handling logic (currently empty)
          });
      });
    }
  }
}

window.customElements.define("typo3-formengine-element-password", PasswordElement);
