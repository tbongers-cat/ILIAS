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
 */

/**
 * @type {String}
 */
const answerSeparator = '{::}';

/**
 * @type {String}
 */
const answerElementClass = 'answers';

/**
 * @type {String}
 */
const placeholderClass = 'c-test__dropzone';

/**
 * @type {DOMElement}
 */
let parentElement;

function setup() {
  const answers = parentElement.querySelectorAll(`.${answerElementClass}`);
  let elementWidth = 0;
  answers.forEach((elem) => { elementWidth += elem.offsetWidth; });
  parentElement.querySelectorAll(`.${placeholderClass}`).forEach(
    (elem) => {
      elem.style.width = `${elementWidth / answers.length}px`;
      elem.style.height = `${answers.item(0).offsetHeight}px`;
    },
  );
}

function updatePlaceholders() {
  const placeholderElement = parentElement.querySelector(`.${placeholderClass}`);

  parentElement.querySelectorAll(`.${answerElementClass}`).forEach(
    (elem) => {
      if (!elem.previousElementSibling?.classList.contains(placeholderClass)) {
        elem.parentNode.insertBefore(placeholderElement.cloneNode(), elem);
      }

      if (!elem.nextElementSibling?.classList.contains(placeholderClass)) {
        elem.parentNode.insertBefore(placeholderElement.cloneNode(), elem.nextElementSibling);
      }
    },
  );

  parentElement.querySelectorAll(`.${placeholderClass} + .${placeholderClass}`).forEach(
    (elem) => {
      elem.remove();
    },
  );
}

function changeHandler() {
  const currentAnswer = [];
  parentElement.querySelectorAll(`.${answerElementClass} > div > span`).forEach(
    (elem) => { currentAnswer.push(elem.textContent); },
  );
  parentElement.nextElementSibling.value = currentAnswer.join(answerSeparator);
}

function onStartPrepareHandler(draggedElement) {
  updatePlaceholders();
  if (draggedElement.previousElementSibling?.classList.contains(placeholderClass)) {
    draggedElement.previousElementSibling.remove();
  }

  if (draggedElement.nextElementSibling?.classList.contains(placeholderClass)) {
    draggedElement.nextElementSibling.remove();
  }
}

export default function orderingHorizontalHandler(parentElementParam, makeDraggable) {
  parentElement = parentElementParam;
  setup();
  makeDraggable(
    'move',
    parentElement,
    answerElementClass,
    placeholderClass,
    changeHandler,
    onStartPrepareHandler,
  );
}
