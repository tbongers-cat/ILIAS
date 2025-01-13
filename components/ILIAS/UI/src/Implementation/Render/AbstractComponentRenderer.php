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

namespace ILIAS\UI\Implementation\Render;

use ILIAS\Data\Factory as DataFactory;
use ILIAS\UI\Component\Component;
use ILIAS\UI\Component\JavaScriptBindable;
use ILIAS\UI\Component\Triggerer;
use ILIAS\UI\Implementation\FactoryInternal;
use ILIAS\UI\HelpTextRetriever;
use ILIAS\UI\Help;
use ILIAS\Language\Language;
use InvalidArgumentException;
use LogicException;
use ILIAS\UI\Implementation\Component\Input\UploadLimitResolver;
use ILIAS\UI\Renderer;

/**
 * Base class for all component renderers.
 *
 * Offers some convenience methods for renderes, users only needs to implement
 * ComponentRenderer::render. Assumes that there is no special resource the
 * component requires.
 */
abstract class AbstractComponentRenderer implements ComponentRenderer, HelpTextRetriever
{
    private static array $component_storage;

    final public function __construct(
        private FactoryInternal $ui_factory,
        private TemplateFactory $tpl_factory,
        private Language $lng,
        private JavaScriptBinding $js_binding,
        private ImagePathResolver $image_path_resolver,
        private DataFactory $data_factory,
        private HelpTextRetriever $help_text_retriever,
        private UploadLimitResolver $upload_limit_resolver,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function registerResources(ResourceRegistry $registry): void
    {
        $registry->register('assets/js/core.js');
    }

    /**
     * Get a UI factory.
     *
     * This could be used to create and render subcomponents like close buttons, etc.
     */
    final protected function getUIFactory(): FactoryInternal
    {
        return $this->ui_factory;
    }

    final protected function getDataFactory(): DataFactory
    {
        return $this->data_factory;
    }

    final protected function getUploadLimitResolver(): UploadLimitResolver
    {
        return $this->upload_limit_resolver;
    }

    /**
     * Get a text from the language file.
     */
    final public function txt(string $id): string
    {
        return $this->lng->txt($id);
    }

    /**
     * Add language var to client side (il.Language)
     * @param mixed $key
     */
    final public function toJS($key): void
    {
        $this->lng->toJS($key);
    }

    /**
     * Get current language key
     */
    public function getLangKey(): string
    {
        return $this->lng->getLangKey();
    }

    final protected function getJavascriptBinding(): JavaScriptBinding
    {
        return $this->js_binding;
    }

    /**
     * Get template of component this renderer is made for.
     *
     * Serves as a wrapper around instantiation of ilTemplate, exposes
     * a smaller interface.
     *
     * @throws	InvalidArgumentException	if there is no such template
     */
    final protected function getTemplate(string $name, bool $purge_unfilled_vars, bool $purge_unused_blocks): Template
    {
        $path = $this->getTemplatePath($name);
        return $this->getTemplateRaw($path, $purge_unfilled_vars, $purge_unused_blocks);
    }

    /**
     * Get the path to the template of this component.
     */
    protected function getTemplatePath(string $name): string
    {
        $component = $this->getMyComponent();
        return "components/ILIAS/UI/src/templates/default/$component/$name";
    }

    /**
     * Get a template from any path.
     */
    private function getTemplateRaw(string $path, bool $purge_unfilled_vars, bool $purge_unused_blocks): Template
    {
        return $this->tpl_factory->getTemplate($path, $purge_unfilled_vars, $purge_unused_blocks);
    }

    /**
     * Bind the component to JavaScript.
     *
     * ATTENTION: If this returns an id, the returned id has to be included as id-attribute
     * into the HTML of your component.
     */
    final protected function bindJavaScript(JavaScriptBindable $component): ?string
    {
        if ($component instanceof Triggerer) {
            $component = $this->addTriggererOnLoadCode($component);
        }
        return $this->bindOnloadCode($component);
    }

    /**
     * Get a fresh unique id.
     *
     * ATTENTION: This does not take care about any usage scenario of the provided
     * id. If you want to use it to bind JS-code to a component, you most probably
     * would want to use bindJavaScript instead, which returns an id that is used
     * to bind js to a component.
     *
     * However, there are cases (e.g radio-input) where an id is required even if
     * there is no javascript involved (e.g. to connect a label with an option),
     * this is where this method could come in handy.
     */
    final protected function createId(): string
    {
        return $this->js_binding->createId();
    }

    /**
     * Bind the JavaScript onload-code.
     */
    private function bindOnloadCode(JavaScriptBindable $component): ?string
    {
        $binder = $component->getOnLoadCode();
        if ($binder === null) {
            return null;
        }

        $id = $this->js_binding->createId();
        $on_load_code = $binder($id);
        if (!is_string($on_load_code)) {
            throw new LogicException(
                "Expected JavaScript binder to return string" .
                " (used component: " . get_class($component) . ")"
            );
        }
        $this->js_binding->addOnLoadCode($on_load_code);
        return $id;
    }

    /**
     * Add onload-code for triggerer.
     */
    private function addTriggererOnLoadCode(Triggerer $triggerer): JavaScriptBindable
    {
        $triggered_signals = $triggerer->getTriggeredSignals();
        if (count($triggered_signals) == 0) {
            return $triggerer;
        }
        return $triggerer->withAdditionalOnLoadCode(function ($id) use ($triggered_signals): string {
            $code = "";
            foreach ($triggered_signals as $triggered_signal) {
                $signal = $triggered_signal->getSignal();
                $event = $triggered_signal->getEvent();
                $options = json_encode($signal->getOptions());
                //Note this switch is necessary since $(#...).on('load', ...) could be fired before the binding of the event.
                //Same seems true fro ready, see: #27456
                if ($event == 'load' || $event == 'ready') {
                    $code .=
                            "$(document).trigger('$signal',
							{
								'id' : '$signal', 'event' : '$event',
								'triggerer' : $('#$id'),
								'options' : JSON.parse('$options')
							}
						);";
                } else {
                    $code .=
                    "$('#$id').on('$event', function(event) {
						$(this).trigger('$signal',
							{
								'id' : '$signal', 'event' : '$event',
								'triggerer' : $(this),
								'options' : JSON.parse('$options')
							}
						);
						return false;
					});";
                }
            }
            return $code;
        });
    }

    /**
     * This method MUST be called by derived component renderers, if @see ComponentRenderer::render()
     * cannot handle the provided component.
     */
    final protected function cannotHandleComponent(Component $component): never
    {
        throw new LogicException(
            sprintf(
                "%s could not render component %s",
                static::class,
                get_class($component)
            )
        );
    }

    /**
     * @return mixed
     */
    private function getMyComponent()
    {
        $class = get_class($this);
        if (isset(self::$component_storage[$class])) {
            return self::$component_storage[$class];
        }
        $matches = array();
        // Extract component
        $re = "%ILIAS\\\\UI\\\\Implementation\\\\Component\\\\(\\w+)\\\\(\\w+)%";
        preg_match($re, $class, $matches);
        if (preg_match($re, $class, $matches) !== 1) {
            throw new LogicException("The Renderer needs to be located in ILIAS\\UI\\Implementation\\Component\\*.");
        }
        self::$component_storage[$class] = $matches[1];

        return self::$component_storage[$class];
    }

    public function getImagePathResolver(): ImagePathResolver
    {
        return $this->image_path_resolver;
    }

    public function getHelpText(Help\Purpose $purpose, Help\Topic ...$topics): array
    {
        return $this->help_text_retriever->getHelpText($purpose, ...$topics);
    }

    /*
     * This is supposed to unify rendering of tooltips over all components.
     */
    protected ?TooltipRenderer $tooltip_renderer = null;

    protected function getTooltipRenderer(): TooltipRenderer
    {
        if ($this->tooltip_renderer === null) {
            $this->tooltip_renderer = new TooltipRenderer(
                $this,
                fn($path, $f1, $f2) => $this->getTemplateRaw($path, $f1, $f2)
            );
        }
        return $this->tooltip_renderer;
    }

    protected function convertSpecialCharacters(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'utf-8');
    }

    public function getComponentCanonicalNameAttribute(Component $component): string
    {
        return str_replace(' ', '-', strtolower($component->getCanonicalName()));
    }
}
