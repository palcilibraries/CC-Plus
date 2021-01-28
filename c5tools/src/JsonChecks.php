<?php
namespace ubfr\c5tools;

trait JsonChecks
{

    /**
     * Check Item.Performance for Total_Item and Access Denied metrics and move them to the component.
     *
     * If components are present Total_Item and Access Denied (i.e. all by Unique_Item) metrics must be reported on the
     * component level. If one of these metrics is present in Item.Performance it is moved to a Component with the same
     * metadata as the Item.
     *
     * @param string $position
     */
    protected function checkItemMetricTypes($position)
    {
        if (! isset($this->currentItem['Performance'])) {
            return;
        }

        $componentPerformance = [];
        foreach ($this->currentItem['Performance'] as $metricType => $dateCounts) {
            if (! preg_match('/Unique_Item_/', $metricType)) {
                $message = 'When Components are present the ';
                if (preg_match('/Total_Item_/', $metricType)) {
                    $message .= 'Totel_Item';
                    $hint = 'if the Item itself was used';
                } else {
                    $message .= 'Access Denied';
                    $hint = 'if access to the Item itself was denied';
                }
                $message .= ' metrics must be reported at the Component level';
                $hint .= ' for a Component with the same metadata as the Item';
                $this->checkResult->addError($message, $message, $position, "Metric_Type '{$metricType}'", $hint);

                $componentPerformance[$metricType] = $dateCounts;
                unset($this->currentItem['Performance'][$metricType]);
            }
        }
        if (! empty($componentPerformance)) {
            // move Total_Item and Access Denied metrics to a copy of the Item as Component
            $this->currentComponent = $this->copyItemToComponent();
            $this->currentComponent['Performance'] = $componentPerformance;
            $this->storeCurrentComponent($position);

            if (empty($this->currentItem['Performance'])) {
                unset($this->currentItem['Performance']);
            }
        }
    }

    /**
     * Check Item_Component.Performance for invalid Unique_Item metrics.
     *
     * The Unique_Item metrics cannot be broken down by Item_Components, they are therefore invalid for
     * Item_Component.Performance. If such a metric is present in Item_Component.Performance it will be removed.
     *
     * @param string $position
     */
    protected function checkComponentMetricTypes($position)
    {
        if (! isset($this->currentComponent['Performance'])) {
            return;
        }

        foreach ($this->currentComponent['Performance'] as $metricType => $dateCounts) {
            if (preg_match('/Unique_Item_/', $metricType)) {
                $message = 'Unique_Item metrics cannot be broken down by Component';
                $this->checkResult->addCriticalError($message, $message, $position, "Metric_Type '{$metricType}'");
                $this->addInvalid('Component', 'Performance', [
                    $metricType => $dateCounts
                ]);
                unset($this->currentComponent['Performance'][$metricType]);
            }
        }
        if (empty($this->currentComponent['Performance'])) {
            unset($this->currentComponent['Performance']);
        }
    }

    protected function checkSectionType($position)
    {
        if ($this->currentSectionTypePosition === null) {
            if ($this->hasMetricType($this->currentItem, '/_Item_/')) {
                $message = "Property 'Section_Type' is missing for Total/Unique_Item metrics";
                $this->checkResult->addCriticalError($message, $message, $position, 'Report_Items');
            }
            return;
        } elseif ($this->hasMetricType($this->currentItem, '/Unique_Title_/')) {
            $sectionType = ($this->currentItem['Section_Type'] ?? '');
            if ($sectionType === '' && isset($this->currentItem['Invalid']) &&
                isset($this->currentItem['Invalid']['Section_Type'])) {
                $sectionType = $this->currentItem['Invalid']['Section_Type'];
            }
            $message = "Property 'Section_Type' is not permitted for Unique_Title metrics";
            $this->checkResult->addCriticalError($message, $message, $this->currentSectionTypePosition,
                $this->formatData('Section_Type', $sectionType));
            return;
        }

        parent::checkSectionType($position);
    }

    protected function checkedArticleType($position, $element, $value, $context)
    {
        // TODO
        return $value;
    }

    protected function checkedQualificationName($position, $element, $value, $context)
    {
        // TODO
        return $value;
    }

    protected function checkedQualificationLevel($position, $element, $value, $context)
    {
        // TODO
        return $value;
    }

    protected function checkedProprietaryAttribute($position, $element, $value, $context)
    {
        // TODO
        return $value;
    }
}
