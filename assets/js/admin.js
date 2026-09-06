(function () {
	var selectAll = document.getElementById("lapsha-select-all");
	if (selectAll) {
		var boxes = function () {
			return Array.prototype.slice.call(
				document.querySelectorAll('input[name="categories[]"]:not([disabled])')
			);
		};

		selectAll.addEventListener("change", function () {
			boxes().forEach(function (box) {
				box.checked = selectAll.checked;
			});
		});
	}
})();

(function () {
	var panels = document.querySelectorAll("[data-lapsha-progress]");
	if (!panels.length) {
		return;
	}

	var i18n = window.lapshaWpTools && window.lapshaWpTools.i18n ? window.lapshaWpTools.i18n : {};

	function formatNumber(value) {
		return Number(value).toLocaleString();
	}

	function bindPanel(panel) {
		var formId = panel.getAttribute("data-form") || "";
		var form = formId ? document.getElementById(formId) : null;
		if (!form) {
			return;
		}

		var button = form.querySelector('button[type="submit"]');
		var overallTemplate = panel.getAttribute("data-overall-template") || i18n.removed || "%1$s of %2$s";
		var stateActive = panel.getAttribute("data-state-active") || i18n.working || "Working…";
		var stateDone = panel.getAttribute("data-state-done") || i18n.done || "Done";
		var running = false;

		function applyProgress(data) {
			var overall = data.overall || {};
			var percent = overall.percent || 0;
			var overallBar = panel.querySelector('[data-role="overall-bar"]');
			var overallWrap = panel.querySelector('[data-role="overall-bar-wrap"]');
			var overallPercent = panel.querySelector('[data-role="overall-percent"]');
			var overallLabel = panel.querySelector('[data-role="overall-label"]');

			if (overallBar) {
				overallBar.style.width = percent + "%";
				overallBar.classList.toggle("is-active", !data.complete);
				overallBar.classList.toggle("is-done", !!data.complete);
			}
			if (overallWrap) {
				overallWrap.setAttribute("aria-valuenow", String(percent));
			}
			if (overallPercent) {
				overallPercent.textContent = percent + "%";
			}
			if (overallLabel) {
				overallLabel.textContent = overallTemplate
					.replace("%1$s", formatNumber(overall.current || 0))
					.replace("%2$s", formatNumber(overall.total || 0));
			}

			(data.items || []).forEach(function (item) {
				var row = panel.querySelector('[data-progress-item="' + item.id + '"]');
				if (!row) {
					return;
				}
				var bar = row.querySelector('[data-role="bar"]');
				var current = row.querySelector('[data-role="current"]');
				var state = row.querySelector('[data-role="state"]');
				var isCurrent = data.current === item.id;

				row.classList.toggle("is-done", !!item.complete);
				row.classList.toggle("is-active", isCurrent && !item.complete);
				if (bar) {
					bar.style.width = (item.percent || 0) + "%";
					bar.classList.toggle("is-done", !!item.complete);
					bar.classList.toggle("is-active", isCurrent && !item.complete);
				}
				if (current) {
					current.textContent = formatNumber(item.current || 0);
				}
				if (state) {
					if (item.complete) {
						state.textContent = stateDone;
					} else if (isCurrent) {
						state.textContent = stateActive;
					} else {
						state.textContent = "";
					}
				}
			});
		}

		function failToSubmit() {
			running = false;
			if (button) {
				button.classList.remove("hidden");
				button.disabled = false;
				button.removeAttribute("aria-hidden");
			}
			form.submit();
		}

		function step() {
			if (running) {
				return;
			}
			running = true;
			if (button) {
				button.disabled = true;
			}

			var body = new FormData(form);
			body.set("lapsha_ajax", "1");

			fetch(form.action, {
				method: "POST",
				body: body,
				credentials: "same-origin",
				headers: {
					Accept: "application/json",
				},
			})
				.then(function (response) {
					return response.json().then(function (payload) {
						return { ok: response.ok, payload: payload };
					});
				})
				.then(function (result) {
					if (!result.payload || !result.payload.success || !result.payload.data) {
						throw new Error("bad-payload");
					}
					var data = result.payload.data;
					applyProgress(data);
					running = false;
					if (data.complete && data.redirect) {
						panel.classList.add("is-complete");
						window.location.href = data.redirect;
						return;
					}
					window.setTimeout(step, 200);
				})
				.catch(function () {
					failToSubmit();
				});
		}

		if (button) {
			button.classList.add("hidden");
			button.setAttribute("aria-hidden", "true");
		}
		form.classList.add("is-automated");

		step();
	}

	Array.prototype.forEach.call(panels, bindPanel);
})();
