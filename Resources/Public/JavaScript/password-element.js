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
import a from "@typo3/core/document-service.js";
import {selector as o} from "@typo3/core/literals.js";
import d from "@typo3/core/ajax/ajax-request.js";
import "@typo3/core/ajax/ajax-response.js";
import t from "@typo3/backend/icons.js";

class m extends HTMLElement {
  constructor() {
    super(...arguments), this.element = null, this.passwordPolicyInfo = null, this.passwordPolicySet = !1
  }

  async connectedCallback() {
    if (this.element !== null) return;
    const s = this.getAttribute("recordFieldId");
    s !== null && (await a.ready(), this.element = this.querySelector(o`#${s}`), this.element && (this.passwordPolicyInfo = this.querySelector(o`#password-policy-info-${this.element.id}`), this.passwordPolicySet = (this.getAttribute("passwordPolicy") || "") !== "", this.registerEventHandler()))
  }

  registerEventHandler() {
    this.passwordPolicySet && this.passwordPolicyInfo !== null && (this.element.addEventListener("focusin", () => {
      this.passwordPolicyInfo.classList.remove("hidden")
    }), this.element.addEventListener("focusout", () => {
      this.passwordPolicyInfo.classList.add("hidden")
    }), this.element.addEventListener("input", () => {
      const s = this.querySelectorAll(o`#password-policy-info-${this.element.id} li`),
        l = Array.from(s).map(i => i.dataset.id);
      new d(TYPO3.settings.ajaxUrls.password_meter_verify).post({
        password: this.element.value,
        list: l
      }).then(async i => {
        const n = await i.resolve();
        n.success === !0 ? s.forEach(e => {
          e.classList.remove("text-danger"), e.classList.remove("text-success"), e.classList.add("text-success")
        }) : s.forEach(e => {
          e.classList.remove("text-danger", "text-success");
          const c = e.querySelector("span");
          n.errors.includes(e.getAttribute("data-id")) ? (t.getIcon("actions-exclamation-circle", t.sizes.small).then(r => {
            c.innerHTML = r
          }), e.classList.add("text-danger")) : (t.getIcon("actions-check-circle", t.sizes.small).then(r => {
            c.innerHTML = r
          }), e.classList.add("text-success"))
        })
      }).catch(() => {
      })
    }))
  }
}

window.customElements.define("typo3-formengine-element-password", m);
