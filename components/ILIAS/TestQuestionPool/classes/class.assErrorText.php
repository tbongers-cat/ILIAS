<?php

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
 *********************************************************************/

declare(strict_types=1);

use ILIAS\TestQuestionPool\Questions\QuestionLMExportable;
use ILIAS\TestQuestionPool\Questions\QuestionAutosaveable;

use ILIAS\Test\Logging\AdditionalInformationGenerator;

/**
 * Class for error text questions
 *
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author		Björn Heyser <bheyser@databay.de>
 * @author		Grégory Saive <gsaive@databay.de>
 * @author		Maximilian Becker <mbecker@databay.de>
 *
 * @version		$Id$
 *
 * @ingroup		ModulesTestQuestionPool
 */
class assErrorText extends assQuestion implements ilObjQuestionScoringAdjustable, ilObjAnswerScoringAdjustable, iQuestionCondition, QuestionLMExportable, QuestionAutosaveable
{
    protected const ERROR_TYPE_WORD = 1;
    protected const ERROR_TYPE_PASSAGE = 2;
    protected const DEFAULT_TEXT_SIZE = 100.0;
    protected const ERROR_MAX_LENGTH = 150;

    protected const PARAGRAPH_SPLIT_REGEXP = '/[\n\r]+/';
    protected const WORD_SPLIT_REGEXP = '/\s+/';
    protected const FIND_PUNCTUATION_REGEXP = '/\p{P}/';
    protected const ERROR_WORD_MARKER = '#';
    protected const ERROR_PARAGRAPH_DELIMITERS = [
        'start' => '((',
        'end' => '))'
    ];

    protected string $errortext = '';
    protected array $parsed_errortext = [];
    /** @var array<assAnswerErrorText> $errordata */
    protected array $errordata = [];
    protected float $textsize;
    protected ?float $points_wrong = null;

    public function __construct(
        string $title = '',
        string $comment = '',
        string $author = '',
        int $owner = -1,
        string $question = ''
    ) {
        parent::__construct($title, $comment, $author, $owner, $question);
        $this->textsize = self::DEFAULT_TEXT_SIZE;
    }

    public function isComplete(): bool
    {
        if (mb_strlen($this->title)
            && ($this->author)
            && ($this->question)
            && ($this->getMaximumPoints() > 0)) {
            return true;
        } else {
            return false;
        }
    }

    public function saveToDb(?int $original_id = null): void
    {
        $this->saveQuestionDataToDb($original_id);
        $this->saveAdditionalQuestionDataToDb();
        $this->saveAnswerSpecificDataToDb();
        parent::saveToDb();
    }

    public function saveAnswerSpecificDataToDb()
    {
        $this->db->manipulateF(
            "DELETE FROM qpl_a_errortext WHERE question_fi = %s",
            ['integer'],
            [$this->getId()]
        );

        $sequence = 0;
        foreach ($this->errordata as $error) {
            $next_id = $this->db->nextId('qpl_a_errortext');
            $this->db->manipulateF(
                "INSERT INTO qpl_a_errortext (answer_id, question_fi, text_wrong, text_correct, points, sequence, position) VALUES (%s, %s, %s, %s, %s, %s, %s)",
                ['integer', 'integer', 'text', 'text', 'float', 'integer', 'integer'],
                [
                    $next_id,
                    $this->getId(),
                    $error->getTextWrong(),
                    $error->getTextCorrect(),
                    $error->getPoints(),
                    $sequence++,
                    $error->getPosition()
                ]
            );
        }
    }

    /**
     * Saves the data for the additional data table.
     *
     * This method uses the ugly DELETE-INSERT. Here, this does no harm.
     */
    public function saveAdditionalQuestionDataToDb()
    {
        $this->db->manipulateF(
            "DELETE FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s",
            ["integer"],
            [$this->getId()]
        );

        $this->db->manipulateF(
            "INSERT INTO " . $this->getAdditionalTableName() . " (question_fi, errortext, parsed_errortext, textsize, points_wrong) VALUES (%s, %s, %s, %s, %s)",
            ["integer", "text", "text", "float", "float"],
            [
                $this->getId(),
                $this->getErrorText(),
                json_encode($this->getParsedErrorText()),
                $this->getTextSize(),
                $this->getPointsWrong()
            ]
        );
    }

    /**
    * Loads the object from the database
    *
    * @param object $db A pear DB object
    * @param integer $question_id A unique key which defines the multiple choice test in the database
    */
    public function loadFromDb($question_id): void
    {
        $db_question = $this->db->queryF(
            "SELECT qpl_questions.*, " . $this->getAdditionalTableName() . ".* FROM qpl_questions LEFT JOIN " . $this->getAdditionalTableName() . " ON " . $this->getAdditionalTableName() . ".question_fi = qpl_questions.question_id WHERE qpl_questions.question_id = %s",
            ["integer"],
            [$question_id]
        );
        if ($db_question->numRows() === 1) {
            $data = $this->db->fetchAssoc($db_question);
            $this->setId($question_id);
            $this->setObjId($data["obj_fi"]);
            $this->setTitle((string) $data["title"]);
            $this->setComment((string) $data["description"]);
            $this->setOriginalId($data["original_id"]);
            $this->setNrOfTries($data['nr_of_tries']);
            $this->setAuthor($data["author"]);
            $this->setPoints($data["points"]);
            $this->setOwner($data["owner"]);
            $this->setQuestion(ilRTE::_replaceMediaObjectImageSrc((string) $data["question_text"], 1));
            $this->setErrorText((string) $data["errortext"]);
            $this->setParsedErrorText(json_decode($data['parsed_errortext'] ?? json_encode([]), true));
            $this->setTextSize($data["textsize"]);
            $this->setPointsWrong($data["points_wrong"]);

            try {
                $this->setLifecycle(ilAssQuestionLifecycle::getInstance($data['lifecycle']));
            } catch (ilTestQuestionPoolInvalidArgumentException $e) {
                $this->setLifecycle(ilAssQuestionLifecycle::getDraftInstance());
            }

            try {
                $this->setAdditionalContentEditingMode($data['add_cont_edit_mode']);
            } catch (ilTestQuestionPoolException $e) {
            }
        }

        $db_error_text = $this->db->queryF(
            "SELECT * FROM qpl_a_errortext WHERE question_fi = %s ORDER BY sequence ASC",
            ['integer'],
            [$question_id]
        );

        if ($db_error_text->numRows() > 0) {
            while ($data = $this->db->fetchAssoc($db_error_text)) {
                $this->errordata[] = new assAnswerErrorText(
                    (string) $data['text_wrong'],
                    (string) $data['text_correct'],
                    (float) $data['points'],
                    $data['position']
                );
            }
        }

        $this->correctDataAfterParserUpdate();

        parent::loadFromDb($question_id);
    }

    private function correctDataAfterParserUpdate(): void
    {
        if ($this->getErrorText() === '') {
            return;
        }
        $needs_finalizing = false;
        if ($this->getParsedErrorText() === []) {
            $needs_finalizing = true;
            $this->parseErrorText();
        }

        if (isset($this->errordata[0])
            && $this->errordata[0]->getPosition() === null) {
            foreach ($this->errordata as $key => $error) {
                $this->errordata[$key] = $this->addPositionToErrorAnswer($error);
            }
            $this->saveAnswerSpecificDataToDb();
        }

        if ($needs_finalizing) {
            $this->completeParsedErrorTextFromErrorData();
            $this->saveAdditionalQuestionDataToDb();
        }
    }

    /**
    * Returns the maximum points, a learner can reach answering the question
    *
    * @see $points
    */
    public function getMaximumPoints(): float
    {
        $maxpoints = 0.0;
        foreach ($this->errordata as $error) {
            if ($error->getPoints() > 0) {
                $maxpoints += $error->getPoints();
            }
        }
        return $maxpoints;
    }

    public function calculateReachedPoints(
        int $active_id,
        ?int $pass = null,
        bool $authorized_solution = true
    ): float {
        if ($pass === null) {
            $pass = $this->getSolutionMaxPass($active_id);
        }
        $result = $this->getCurrentSolutionResultSet($active_id, $pass, $authorized_solution);

        $positions = [];
        while ($row = $this->db->fetchAssoc($result)) {
            $positions[] = $row['value1'];
        }
        $points = $this->getPointsForSelectedPositions($positions);
        return $points;
    }

    public function calculateReachedPointsFromPreviewSession(ilAssQuestionPreviewSession $preview_session)
    {
        $reached_points = $this->getPointsForSelectedPositions($preview_session->getParticipantsSolution() ?? []);
        $reached_points = $this->deductHintPointsFromReachedPoints($preview_session, $reached_points);
        return $this->ensureNonNegativePoints($reached_points);
    }

    public function saveWorkingData(
        int $active_id,
        ?int $pass = null,
        bool $authorized = true
    ): bool {
        if (is_null($pass)) {
            $pass = ilObjTest::_getPass($active_id);
        }

        $this->getProcessLocker()->executeUserSolutionUpdateLockOperation(
            function () use ($active_id, $pass, $authorized) {
                $selected = $this->getAnswersFromRequest();
                $this->removeCurrentSolution($active_id, $pass, $authorized);
                foreach ($selected as $position) {
                    $this->saveCurrentSolution($active_id, $pass, $position, null, $authorized);
                }
            }
        );

        return true;
    }

    public function savePreviewData(ilAssQuestionPreviewSession $previewSession): void
    {
        $selection = $this->getAnswersFromRequest();
        $previewSession->setParticipantsSolution($selection);
    }

    private function getAnswersFromRequest(): array
    {
        if (mb_strlen($_POST["qst_" . $this->getId()])) {
            return explode(',', $_POST["qst_{$this->getId()}"]);
        }

        return [];
    }

    public function getQuestionType(): string
    {
        return 'assErrorText';
    }

    public function getAdditionalTableName(): string
    {
        return 'qpl_qst_errortext';
    }

    public function getAnswerTableName(): string
    {
        return 'qpl_a_errortext';
    }

    public function setErrorsFromParsedErrorText(): void
    {
        $current_error_data = $this->getErrorData();
        $this->errordata = [];

        $has_too_long_errors = false;
        foreach ($this->getParsedErrorText() as $paragraph) {
            foreach ($paragraph as $position => $word) {
                if ($word['error_type'] === 'in_passage'
                    || $word['error_type'] === 'passage_end'
                    || $word['error_type'] === 'none') {
                    continue;
                }

                $text_wrong = $word['text_wrong'];
                if (mb_strlen($text_wrong) > self::ERROR_MAX_LENGTH) {
                    $has_too_long_errors = true;
                    continue;
                }

                list($text_correct, $points) =
                    $this->getAdditionalInformationFromExistingErrorDataByErrorText($current_error_data, $text_wrong);
                $this->errordata[] = new assAnswerErrorText($text_wrong, $text_correct, $points, $position);
            }
        }

        if ($has_too_long_errors) {
            $this->tpl->setOnScreenMessage(
                'failure',
                $this->lng->txt('qst_error_text_too_long')
            );
        }
    }

    private function addPositionToErrorAnswer(assAnswerErrorText $error): assAnswerErrorText
    {
        foreach ($this->getParsedErrorText() as $paragraph) {
            foreach ($paragraph as $position => $word) {
                if (isset($word['text_wrong'])
                    && ($word['text_wrong'] === $error->getTextWrong()
                        || mb_substr($word['text_wrong'], 0, -1) === $error->getTextWrong()
                            && preg_match(self::FIND_PUNCTUATION_REGEXP, mb_substr($word['text_wrong'], -1)) === 1)
                    && !array_key_exists($position, $this->generateArrayByPositionFromErrorData())
                ) {
                    return $error->withPosition($position);
                }
            }
        }

        return $error;
    }

    private function completeParsedErrorTextFromErrorData(): void
    {
        foreach ($this->errordata as $error) {
            $position = $error->getPosition();
            foreach ($this->getParsedErrorText() as $key => $paragraph) {
                if (array_key_exists($position, $paragraph)) {
                    $this->parsed_errortext[$key][$position]['text_correct'] =
                        $error->getTextCorrect();
                    $this->parsed_errortext[$key][$position]['points'] =
                        $error->getPoints();
                    break;
                }
            }
        }
    }

    /**
     *
     * @param array<assAnswerErrorText> $errors
     */
    public function setErrorData(array $errors): void
    {
        $this->errordata = [];

        foreach ($errors as $error) {
            $answer = $this->addPositionToErrorAnswer($error);
            $this->errordata[] = $answer;
        }
        $this->completeParsedErrorTextFromErrorData();
    }

    public function removeErrorDataWithoutPosition(): void
    {
        foreach ($this->getErrorData() as $index => $error) {
            if ($error->getPosition() === null) {
                unset($this->errordata[$index]);
            }
        }
        $this->errordata = array_values($this->errordata);
    }

    /**
     *
     * @param array<assAnswerErrorText> $current_error_data
     * @return array<mixed>
     */
    private function getAdditionalInformationFromExistingErrorDataByErrorText(
        array $current_error_data,
        string $text_wrong
    ): array {
        foreach ($current_error_data as $answer_object) {
            if (strcmp($answer_object->getTextWrong(), $text_wrong) === 0) {
                return[
                    $answer_object->getTextCorrect(),
                    $answer_object->getPoints()
                ];
            }
        }
        return ['', 0.0];
    }

    public function assembleErrorTextOutput(
        array $selections,
        bool $graphical_output = false,
        bool $show_correct_solution = false,
        bool $use_link_tags = true,
        array $correctness_icons = []
    ): string {
        $output_array = [];
        foreach ($this->getParsedErrorText() as $paragraph) {
            $array_reduce_function = fn(?string $carry, int $position)
                => $carry . $this->generateOutputStringFromPosition(
                    $position,
                    $selections,
                    $paragraph,
                    $graphical_output,
                    $show_correct_solution,
                    $use_link_tags,
                    $correctness_icons
                );
            $output_array[] = '<p>' . trim(array_reduce(array_keys($paragraph), $array_reduce_function)) . '</p>';
        }

        return implode("\n", $output_array);
    }

    private function generateOutputStringFromPosition(
        int $position,
        array $selections,
        array $paragraph,
        bool $graphical_output,
        bool $show_correct_solution,
        bool $use_link_tags,
        array $correctness_icons
    ): string {
        $text = $this->getTextForPosition($position, $paragraph, $show_correct_solution);
        if ($text === '') {
            return '';
        }
        $class = $this->getClassForPosition($position, $show_correct_solution, $selections);
        $img = $this->getCorrectnessIconForPosition(
            $position,
            $graphical_output,
            $selections,
            $correctness_icons
        );

        return ' ' . $this->getErrorTokenHtml($text, $class, $use_link_tags) . $img;
    }

    private function getTextForPosition(
        int $position,
        array $paragraph,
        bool $show_correct_solution
    ): string {
        $v = $paragraph[$position];
        if ($show_correct_solution === true
            && ($v['error_type'] === 'in_passage'
            || $v['error_type'] === 'passage_end')) {
            return '';
        }
        if ($show_correct_solution
            && ($v['error_type'] === 'passage_start'
            || $v['error_type'] === 'word')) {
            return $v['text_correct'] ?? '';
        }

        return $v['text'];
    }

    private function getClassForPosition(
        int $position,
        bool $show_correct_solution,
        array $selections
    ): string {
        if ($show_correct_solution !== true
            && in_array($position, $selections['user'])) {
            return 'ilc_qetitem_ErrorTextSelected';
        }

        if ($show_correct_solution === true
            && in_array($position, $selections['best'])) {
            return 'ilc_qetitem_ErrorTextSelected';
        }

        return 'ilc_qetitem_ErrorTextItem';
    }

    private function getCorrectnessIconForPosition(
        int $position,
        bool $graphical_output,
        array $selections,
        array $correctness_icons
    ): string {
        if ($graphical_output === true
             && (in_array($position, $selections['user']) && !in_array($position, $selections['best'])
             || !in_array($position, $selections['user']) && in_array($position, $selections['best']))) {
            return $correctness_icons['not_correct'];
        }

        if ($graphical_output === true
            && in_array($position, $selections['user']) && in_array($position, $selections['best'])) {
            return $correctness_icons['correct'];
        }

        return '';
    }

    public function createErrorTextExport(array $selections): string
    {
        if (!is_array($selections)) {
            $selections = [];
        }

        foreach ($this->getParsedErrorText() as $paragraph) {
            $array_reduce_function = function ($carry, $k) use ($paragraph, $selections) {
                $text = $paragraph[$k]['text'];
                if (in_array($k, $selections)) {
                    $text = self::ERROR_WORD_MARKER . $paragraph[$k]['text'] . self::ERROR_WORD_MARKER;
                }
                return $carry . ' ' . $text;
            };
            $output_array[] = trim(array_reduce(array_keys($paragraph), $array_reduce_function));
        }
        return implode("\n", $output_array);
    }

    public function getBestSelection(bool $with_positive_points_only = true): array
    {
        $positions_array = $this->generateArrayByPositionFromErrorData();
        $selections = [];
        foreach ($positions_array as $position => $position_data) {
            if ($position === ''
                || $with_positive_points_only && $position_data['points'] <= 0) {
                continue;
            }

            $selections[] = $position;
            if ($position_data['length'] > 1) {
                for ($i = 1;$i < $position_data['length'];$i++) {
                    $selections[] = $position + $i;
                }
            }
        }

        return $selections;
    }

    /**
     *
     * @param list<string>|null $selected_words Positions of Selected Words Counting from 0
     */
    protected function getPointsForSelectedPositions(array $selected_word_positions): float
    {
        $points = 0;
        $correct_positions = $this->generateArrayByPositionFromErrorData();

        foreach ($correct_positions as $correct_position => $correct_position_data) {
            $selected_word_key = array_search($correct_position, $selected_word_positions);
            if ($selected_word_key === false) {
                continue;
            }

            if ($correct_position_data['length'] === 1) {
                $points += $correct_position_data['points'];
                unset($selected_word_positions[$selected_word_key]);
                continue;
            }

            $passage_complete = true;
            for ($i = 1;$i < $correct_position_data['length'];$i++) {
                $selected_passage_element_key = array_search($correct_position + $i, $selected_word_positions);
                if ($selected_passage_element_key === false) {
                    $passage_complete = false;
                    continue;
                }
                unset($selected_word_positions[$selected_passage_element_key]);
            }

            if ($passage_complete) {
                $points += $correct_position_data['points'];
                unset($selected_word_positions[$selected_word_key]);
            }
        }

        foreach ($selected_word_positions as $word_position) {
            if (!array_key_exists($word_position, $correct_positions)) {
                $points += $this->getPointsWrong();
                continue;
            }
        }

        return $points;
    }

    public function flushErrorData(): void
    {
        $this->errordata = [];
    }

    /**
     *
     * @return array<assAnswerErrorText>
     */
    public function getErrorData(): array
    {
        return $this->errordata;
    }

    /**
     *
     * @return array<mixed>
     */
    private function getErrorDataAsArrayForJS(): array
    {
        $correct_answers = [];
        foreach ($this->getErrorData() as $index => $answer_obj) {
            $correct_answers[] = [
                'answertext_wrong' => $answer_obj->getTextWrong(),
                'answertext_correct' => $answer_obj->getTextCorrect(),
                'points' => $answer_obj->getPoints(),
                'length' => $answer_obj->getLength(),
                'pos' => $this->getId() . '_' . $answer_obj->getPosition()
            ];
        }
        return $correct_answers;
    }

    public function getErrorText(): string
    {
        return $this->errortext ?? '';
    }

    public function setErrorText(?string $text): void
    {
        $this->errortext = $text ?? '';
    }

    public function getParsedErrorText(): array
    {
        return $this->parsed_errortext;
    }

    private function getParsedErrorTextForJS(): array
    {
        $answers = [];
        foreach ($this->parsed_errortext as $paragraph) {
            foreach ($paragraph as $position => $word) {
                $answers[] = [
                    'answertext' => $word['text'],
                    'order' => $this->getId() . '_' . $position
                ];
            }
            $answers[] = [
                'answertext' => '###'
            ];
        }
        array_pop($answers);

        return $answers;
    }

    public function setParsedErrorText(array $parsed_errortext): void
    {
        $this->parsed_errortext = $parsed_errortext;
    }

    public function getTextSize(): float
    {
        return $this->textsize;
    }

    public function setTextSize($a_value): void
    {
        // in self-assesment-mode value should always be set (and must not be null)
        if ($a_value === null) {
            $a_value = 100;
        }
        $this->textsize = $a_value;
    }

    public function getPointsWrong(): ?float
    {
        return $this->points_wrong;
    }

    public function setPointsWrong($a_value): void
    {
        $this->points_wrong = $a_value;
    }

    public function toJSON(): string
    {
        $result = [];
        $result['id'] = $this->getId();
        $result['type'] = (string) $this->getQuestionType();
        $result['title'] = $this->getTitle();
        $result['question'] = $this->formatSAQuestion($this->getQuestion());
        $result['text'] = ilRTE::_replaceMediaObjectImageSrc($this->getErrorText(), 0);
        $result['nr_of_tries'] = $this->getNrOfTries();
        $result['shuffle'] = $this->getShuffle();
        $result['feedback'] = [
            'onenotcorrect' => $this->formatSAQuestion($this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), false)),
            'allcorrect' => $this->formatSAQuestion($this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), true))
        ];

        $result['correct_answers'] = $this->getErrorDataAsArrayForJS();
        $result['answers'] = $this->getParsedErrorTextForJS();

        $mobs = ilObjMediaObject::_getMobsOfObject("qpl:html", $this->getId());
        $result['mobs'] = $mobs;

        return json_encode($result);
    }

    public function getOperators(string $expression): array
    {
        return ilOperatorsExpressionMapping::getOperatorsByExpression($expression);
    }

    public function getExpressionTypes(): array
    {
        return [
            iQuestionCondition::PercentageResultExpression,
            iQuestionCondition::NumberOfResultExpression,
            iQuestionCondition::EmptyAnswerExpression,
            iQuestionCondition::ExclusiveResultExpression
        ];
    }

    public function getUserQuestionResult(
        int $active_id,
        int $pass
    ): ilUserQuestionResult {
        $result = new ilUserQuestionResult($this, $active_id, $pass);

        $data = $this->db->queryF(
            "SELECT value1+1 as value1 FROM tst_solutions WHERE active_fi = %s AND pass = %s AND question_fi = %s AND step = (
				SELECT MAX(step) FROM tst_solutions WHERE active_fi = %s AND pass = %s AND question_fi = %s
			)",
            ["integer", "integer", "integer","integer", "integer", "integer"],
            [$active_id, $pass, $this->getId(), $active_id, $pass, $this->getId()]
        );

        while ($row = $this->db->fetchAssoc($data)) {
            $result->addKeyValue($row["value1"], $row["value1"]);
        }

        $points = $this->calculateReachedPoints($active_id, $pass);
        $max_points = $this->getMaximumPoints();

        $result->setReachedPercentage(($points / $max_points) * 100);

        return $result;
    }

    public function parseErrorText(): void
    {
        $text_by_paragraphs = preg_split(self::PARAGRAPH_SPLIT_REGEXP, $this->getErrorText());
        $text_array = [];
        $offset = 0;
        foreach ($text_by_paragraphs as $paragraph) {
            $text_array[] = $this->addErrorInformationToTextParagraphArray(
                preg_split(self::WORD_SPLIT_REGEXP, trim($paragraph)),
                $offset
            );
            $offset += count(end($text_array));
        }
        $this->setParsedErrorText($text_array);
    }

    /**
     *
     * @param list<string> $paragraph
     * @return array<string|array>
     */
    private function addErrorInformationToTextParagraphArray(array $paragraph, int $offset): array
    {
        $paragraph_with_error_info = [];
        $passage_start = null;
        foreach ($paragraph as $position => $word) {
            $actual_position = $position + $offset;
            if ($passage_start !== null
                && (mb_strrpos($word, self::ERROR_PARAGRAPH_DELIMITERS['end']) === mb_strlen($word) - 2
                || mb_strrpos($word, self::ERROR_PARAGRAPH_DELIMITERS['end']) === mb_strlen($word) - 3
                    && preg_match(self::FIND_PUNCTUATION_REGEXP, mb_substr($word, -1)) === 1)) {
                $actual_word = $this->parsePassageEndWord($word);

                $paragraph_with_error_info[$passage_start]['text_wrong'] .=
                    ' ' . $actual_word;
                $paragraph_with_error_info[$actual_position] = [
                    'text' => $actual_word,
                    'error_type' => 'passage_end'
                ];
                $passage_start = null;
                continue;
            }
            if ($passage_start !== null) {
                $paragraph_with_error_info[$passage_start]['text_wrong'] .= ' ' . $word;
                $paragraph_with_error_info[$actual_position] = [
                    'text' => $word,
                    'error_type' => 'in_passage'
                ];
                continue;
            }
            if (mb_strpos($word, self::ERROR_PARAGRAPH_DELIMITERS['start']) === 0) {
                $paragraph_with_error_info[$actual_position] = [
                    'text' => substr($word, 2),
                    'text_wrong' => substr($word, 2),
                    'error_type' => 'passage_start',
                    'error_position' => $actual_position,
                ];
                $passage_start = $actual_position;
                continue;
            }
            if (mb_strpos($word, self::ERROR_WORD_MARKER) === 0) {
                $paragraph_with_error_info[$actual_position] = [
                    'text' => substr($word, 1),
                    'text_wrong' => substr($word, 1),
                    'error_type' => 'word',
                    'error_position' => $actual_position,
                ];
                continue;
            }

            $paragraph_with_error_info[$actual_position] = [
                'text' => $word,
                'error_type' => 'none',
                'points' => $this->getPointsWrong()
            ];
        }

        return $paragraph_with_error_info;
    }

    private function parsePassageEndWord(string $word): string
    {
        if (mb_substr($word, -2) === self::ERROR_PARAGRAPH_DELIMITERS['end']) {
            return mb_substr($word, 0, -2);
        }
        return mb_substr($word, 0, -3) . mb_substr($word, -1);
    }

    /**
     * If index is null, the function returns an array with all anwser options
     * Else it returns the specific answer option
     *
     * @param null|int $index
     *
     */
    public function getAvailableAnswerOptions($index = null): ?int
    {
        $error_text_array = array_reduce(
            $this->parsed_errortext,
            fn($c, $v) => $c + $v
        );

        if ($index === null) {
            return $error_text_array;
        }

        if (array_key_exists($index, $error_text_array)) {
            return $error_text_array[$index];
        }

        return null;
    }

    private function generateArrayByPositionFromErrorData(): array
    {
        $array_by_position = [];
        foreach ($this->errordata as $error) {
            $array_by_position[$error->getPosition()] = [
                'length' => $error->getLength(),
                'points' => $error->getPoints(),
                'text' => $error->getTextWrong(),
                'text_correct' => $error->getTextCorrect()
            ];
        }
        ksort($array_by_position);
        return $array_by_position;
    }

    /**
     * @param $item
     * @param $class
     * @return string
     */
    private function getErrorTokenHtml($item, $class, $useLinkTags): string
    {
        if ($useLinkTags) {
            return '<a class="' . $class . '" href="#">' . ($item == '&nbsp;' ? $item : ilLegacyFormElementsUtil::prepareFormOutput(
                $item
            )) . '</a>';
        }

        return '<span class="' . $class . '">' . ($item == '&nbsp;' ? $item : ilLegacyFormElementsUtil::prepareFormOutput(
            $item
        )) . '</span>';
    }

    public function toLog(AdditionalInformationGenerator $additional_info): array
    {
        $result = [
            AdditionalInformationGenerator::KEY_QUESTION_TYPE => (string) $this->getQuestionType(),
            AdditionalInformationGenerator::KEY_QUESTION_TITLE => $this->getTitle(),
            AdditionalInformationGenerator::KEY_QUESTION_TEXT => $this->formatSAQuestion($this->getQuestion()),
            AdditionalInformationGenerator::KEY_QUESTION_ERRORTEXT_ERRORTEXT => ilRTE::_replaceMediaObjectImageSrc($this->getErrorText(), 0),
            AdditionalInformationGenerator::KEY_QUESTION_SHUFFLE_ANSWER_OPTIONS => $additional_info
                ->getTrueFalseTagForBool($this->getShuffle()),
            AdditionalInformationGenerator::KEY_FEEDBACK => [
                AdditionalInformationGenerator::KEY_QUESTION_FEEDBACK_ON_INCOMPLETE => $this->formatSAQuestion($this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), false)),
                AdditionalInformationGenerator::KEY_QUESTION_FEEDBACK_ON_COMPLETE => $this->formatSAQuestion($this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), true))
            ]
        ];

        $error_data = $this->getErrorData();
        $result[AdditionalInformationGenerator::KEY_QUESTION_CORRECT_ANSWER_OPTIONS] = array_reduce(
            array_keys($error_data),
            static function (array $c, int $k) use ($error_data): array {
                $c[$k + 1] = [
                    'text_wrong' => $error_data[$k]->getTextWrong(),
                    'text_correct' => $error_data[$k]->getTextCorrect(),
                    'points' => $error_data[$k]->getPoints()
                ];
                return $c;
            },
            []
        );

        return $result;
    }

    protected function solutionValuesToLog(
        AdditionalInformationGenerator $additional_info,
        array $solution_values
    ): string {
        return $this->createErrorTextExport(
            array_map(
                static fn(string $v): string => $v['value1'],
                $solution_values
            )
        );
    }

    public function solutionValuesToText(array $solution_values): string
    {
        return $this->createErrorTextExport(
            array_map(
                static fn(string $v): string => $v['value1'],
                $solution_values
            )
        );
    }

    public function getCorrectSolutionForTextOutput(int $active_id, int $pass): string
    {
        return $this->createErrorTextExport($this->getBestSelection());
    }
}
