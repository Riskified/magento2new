<?php
namespace Riskified\Decider\Model;

trait DateFormatter {
    /**
     * @param $dateStr
     *
     * @return false|null|string
     */
    public function formatDateAsIso8601($dateStr)
    {
        return ($dateStr == null) ? null : date('c', strtotime($dateStr));
    }
}
