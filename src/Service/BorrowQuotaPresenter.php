<?php

namespace App\Service;

use App\Entity\Users;
use App\Repository\BorrowsRepository;
use App\Repository\SettingsRepository;

/**
 * Resolves effective borrow limits for the book detail borrow panel (UI only).
 */
final class BorrowQuotaPresenter
{
    public function __construct(
        private readonly BorrowsRepository $borrowsRepository,
        private readonly SettingsRepository $settingsRepository,
    ) {}

    /**
     * @return array{
     *     effectiveBorrowLimit: int,
     *     effectiveMaxBorrowDays: int,
     *     remainingBorrowSlots: int,
     *     activeBorrowCount: int
     * }
     */
    public function forMemberAndBook(Users $user, ?int $bookBorrowDaysLimit): array
    {
        $defaultLimit = $this->getIntSetting('default_borrow_limit', 5);
        $defaultDays  = $this->getIntSetting('default_borrow_days', 14);

        $effectiveBorrowLimit   = $user->getBorrowLimit() ?? $defaultLimit;
        $effectiveMaxBorrowDays = $bookBorrowDaysLimit ?? $defaultDays;

        $userId       = (int) $user->getId();
        $activeCount  = $this->borrowsRepository->countActiveForMember($userId);
        $remaining    = max(0, $effectiveBorrowLimit - $activeCount);

        return [
            'effectiveBorrowLimit'   => $effectiveBorrowLimit,
            'effectiveMaxBorrowDays' => $effectiveMaxBorrowDays,
            'remainingBorrowSlots'   => $remaining,
            'activeBorrowCount'      => $activeCount,
        ];
    }

    private function getIntSetting(string $key, int $default): int
    {
        $row = $this->settingsRepository->findOneBy(['key' => $key]);
        if ($row === null || $row->getValue() === null || $row->getValue() === '') {
            return $default;
        }

        if (!is_numeric($row->getValue())) {
            return $default;
        }

        return (int) $row->getValue();
    }
}
