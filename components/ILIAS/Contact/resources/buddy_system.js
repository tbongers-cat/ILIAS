/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 ******************************************************************** */

(function ($, $scope) {
  $scope.il.BuddySystem = {
    config: {},

    setConfig(config) {
      const bs = $scope.il.BuddySystem;
      bs.config = config;
    },
  };

  $scope.il.BuddySystemButton = {
    config: {},

    setConfig(config) {
      const btn = $scope.il.BuddySystemButton;
      btn.config = config;
    },

    init() {
      const toggle =  '[data-toggle="dropdown"]';
      const btn = $scope.il.BuddySystemButton;
      const bs = $scope.il.BuddySystem;
      const trigger_selector = 'a[data-target-state], button[data-target-state]';

      const onWidgetClick = function onWidgetClick(e) {
        const $trigger = $(this);

        if ($trigger.data('submitted') === true) {
          // Prevent concurrent requests
          return;
        }

        e.preventDefault();
        e.stopPropagation();

        const $container = $trigger.closest(`.${btn.config.bnt_class}`);

        const values = {};
        values.usr_id = $container.data('buddy-id');
        values.action = $trigger.data('action');
        values[`cmd[${bs.config.transition_state_cmd}]`] = 1;

        const promise = $.ajax({
          url: bs.config.http_post_url,
          type: 'POST',
          data: values,
          dataType: 'json',
          beforeSend() {
            $(`.${btn.config.bnt_class}`).filter(function () {
              return $(this).data('buddy-id') == $container.data('buddy-id');
            }).each(function () {
              const container = $(this);
              container.find(trigger_selector)
                .data('submitted', true)
                .attr('disabled', true);
            });
          },
        });

        promise.done((response) => {
          const state = $container.data('current-state');

          if (response.success !== undefined) {
            if (response.state !== undefined && response.state_html !== undefined) {
              if (state != response.state) {
                $($scope).trigger('il.bs.stateChange.beforeButtonWidgetReRendered', [$container.data('buddy-id'), response.state, state]);

                $(`.${btn.config.bnt_class}`).filter(function () {
                  return $(this).data('buddy-id') == $container.data('buddy-id');
                }).each(function () {
                  const container = $(this);
                  container.find('.button-container').html(response.state_html);
                  container.data('current-state', response.state);
                });

                $($scope).trigger('il.bs.stateChange.afterButtonWidgetReRendered', [$container.data('buddy-id'), response.state, state]);
              }
            }
          }

          $(`.${btn.config.bnt_class}`).filter(function () {
            return $(this).data('buddy-id') == $container.data('buddy-id');
          }).each(function () {
            const container = $(this);
            container.find(trigger_selector)
              .data('submitted', false)
              .attr('disabled', false);
          });

          if (response.message !== undefined) {
            $container.find('button').popover({
              container: 'body',
              content: response.message,
              placement: 'auto',
              trigger: 'focus',
            }).popover('show');
            $container.find('button').focus().on('hidden.bs.popover', function () {
              $(this).popover('destroy');
            });
          }

          $($scope).trigger('il.bs.stateChange.afterStateChangePerformed', [$container.data('buddy-id'), $container.data('current-state'), state]);
        }).fail(() => {
          $(`.${btn.config.bnt_class}`).filter(function () {
            return $(this).data('buddy-id') == $container.data('buddy-id');
          }).each(function () {
            const container = $(this);
            container.find(trigger_selector)
              .data('submitted', false)
              .attr('disabled', false);
          });
        });
      };

      $($scope).on('il.bs.stateChange.afterStateChangePerformed', (event, usr_id, is_state, was_state) => {
        if (
          (was_state === 'ilBuddySystemLinkedRelationState' || was_state === 'ilBuddySystemRequestedRelationState') && is_state !== was_state
        ) {
          if (typeof il.Awareness !== 'undefined') {
            il.Awareness.reload();
          }
        }
        return true;
      });

      // This is is a listener for the case, that the "Buddy System Widget" is asynchronously added to the DOM (currently not used in the ILIAS core)
      $($scope).on('il.bs.domelement.added', (ev, id) => {
        $(`#${id}`).find(`.${btn.config.bnt_class}`).on('click', trigger_selector, onWidgetClick);
      });

      const clearAllDropDowns = function (e) {
        const trigger_buttons = document.querySelectorAll(toggle);
        trigger_buttons.forEach((trigger_button) => {
          const parent = trigger_button.parentElement;

          if (!parent.classList.contains('open')) {
            return;
          }

          if (e && e.defaultPrevented) {
            return;
          }

          trigger_button.setAttribute('aria-expanded', 'false');
          parent.classList.remove('open');
          parent.querySelector(':scope > .dropdown-menu').style.display = 'none';
        });

        // Hide UI dropdowns
        if (il && il.UI && il.UI.dropdown) {
          il.UI.dropdown.opened?.hide();
        }
      };

      $(`.${btn.config.bnt_class}`)
        .attr('aria-live', 'polite')
        .on('keydown', toggle, function (e) {
          if (!/(38|40|27|32)/.test(e.which)) {
            return;
          }

          e.preventDefault();
          e.stopPropagation();

          const trigger_button = $(this).get(0);
          const parent = trigger_button.parentElement;
          const is_active = parent.classList.contains('open');

          if (!is_active && e.which !== 27 || is_active && e.which === 27) {
            if (e.which === 27) {
              parent.querySelector(toggle).focus();
            }

            trigger_button.click();
            return;
          }

          const items = Array.from(parent.querySelectorAll('.dropdown-menu li a')).filter((item) => item.offsetParent !== null);
          if (!items.length) {
            return;
          }

          let index = Array.prototype.indexOf.call(items, e.target);
          if (e.which === 38 && index > 0) {
            index--; // up
          }
          if (e.which === 40 && index < items.length - 1) {
            index++; // down
          }
          if (index === -1) {
            index = 0;
          }

          items[index].focus();
        })
        .on('click', toggle, function (e) {
          if (e && e.which === 3) {
            // Do nothing on right clicks
            return;
          }

          const trigger_button = $(this).get(0);
          const parent = trigger_button.parentElement;
          const is_active = parent.classList.contains('open');

          clearAllDropDowns();

          if (!is_active) {
            if (e.defaultPrevented) {
              return;
            }

            const drop_down = parent.querySelector(':scope > .dropdown-menu');
            drop_down.style.display = 'block';

            const available_width = parent.ownerDocument.documentElement.clientWidth;
            const button_position = trigger_button.getBoundingClientRect().left;
            const list_width = drop_down.getBoundingClientRect().width;

            if (button_position + list_width > available_width) {
              drop_down.classList.remove('dropdown-menu__right');
              drop_down.classList.add('dropdown-menu__left');
            } else {
              drop_down.classList.remove('dropdown-menu__left');
              drop_down.classList.add('dropdown-menu__right');
            }

            trigger_button.focus();
            trigger_button.setAttribute('aria-expanded', 'true');
            parent.classList.toggle('open');
          }

          return false;
        })
        .on('click', trigger_selector, onWidgetClick);

      $(document).on('click', clearAllDropDowns);
    },
  };

  $(document).ready(() => {
    $('#awareness_trigger').on('awrn:shown', (event) => {
      $('#awareness-content').find('a[data-target-state]').off('click').on('click', function (e) {
        const bs = $scope.il.BuddySystem;
        const $elm = $(this);
        const usr_id = $elm.data('buddy-id');

        e.preventDefault();
        e.stopPropagation();

        const values = {};
        values.usr_id = usr_id;
        values.action = $elm.data('action');
        values[`cmd[${bs.config.transition_state_cmd}]`] = 1;

        const promise = $.ajax({
          url: bs.config.http_post_url,
          type: 'POST',
          data: values,
          dataType: 'json',
          beforeSend() {
          },
        });

        promise.done((response) => {
          const state = $elm.data('current-state');
          if (response.success !== undefined) {
            if (response.state !== undefined) {
              if (state !== response.state) {
                $($scope).trigger('il.bs.stateChange.afterStateChangePerformed', [usr_id, response.state, state]);
              }
            }
          }
        });
      });
    });
  });
}(jQuery, window));
