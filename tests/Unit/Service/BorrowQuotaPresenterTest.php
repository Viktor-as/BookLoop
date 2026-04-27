<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Settings;
use App\Entity\Users;
use App\Repository\BorrowsRepository;
use App\Repository\SettingsRepository;
use App\Service\BorrowQuotaPresenter;
use PHPUnit\Framework\TestCase;

final class BorrowQuotaPresenterTest extends TestCase
{
    public function testUsesDefaultsWhenNoSettingsAndNoUserOverride(): void
    {
        $borrows = $this->createMock(BorrowsRepository::class);
        $borrows->method('countActiveForMember')->with(10)->willReturn(2);

        $settings = $this->createMock(SettingsRepository::class);
        $settings->method('findOneBy')->willReturn(null);

        $user = $this->createConfiguredMock(Users::class, [
            'getId' => 10,
            'getBorrowLimit' => null,
        ]);

        $presenter = new BorrowQuotaPresenter($borrows, $settings);
        $q = $presenter->forMemberAndBook($user, 21);

        self::assertSame(5, $q['effectiveBorrowLimit']);
        self::assertSame(21, $q['effectiveMaxBorrowDays']);
        self::assertSame(3, $q['remainingBorrowSlots']);
        self::assertSame(2, $q['activeBorrowCount']);
    }

    public function testUserBorrowLimitOverridesDefault(): void
    {
        $borrows = $this->createMock(BorrowsRepository::class);
        $borrows->method('countActiveForMember')->willReturn(1);

        $settings = $this->createMock(SettingsRepository::class);
        $settings->method('findOneBy')->willReturn(null);

        $user = $this->createConfiguredMock(Users::class, [
            'getId' => 1,
            'getBorrowLimit' => 4,
        ]);

        $presenter = new BorrowQuotaPresenter($borrows, $settings);
        $q = $presenter->forMemberAndBook($user, null);

        self::assertSame(4, $q['effectiveBorrowLimit']);
        self::assertSame(14, $q['effectiveMaxBorrowDays']);
        self::assertSame(3, $q['remainingBorrowSlots']);
    }

    public function testNumericSettingsOverrideDefaults(): void
    {
        $borrows = $this->createMock(BorrowsRepository::class);
        $borrows->method('countActiveForMember')->willReturn(0);

        $settings = $this->createMock(SettingsRepository::class);
        $settings->method('findOneBy')->willReturnCallback(function (array $criteria): ?Settings {
            $key = $criteria['key'] ?? '';
            if ('default_borrow_limit' === $key) {
                return (new Settings())->setKey($key)->setValue('8');
            }
            if ('default_borrow_days' === $key) {
                return (new Settings())->setKey($key)->setValue('30');
            }

            return null;
        });

        $user = $this->createConfiguredMock(Users::class, [
            'getId' => 1,
            'getBorrowLimit' => null,
        ]);

        $presenter = new BorrowQuotaPresenter($borrows, $settings);
        $q = $presenter->forMemberAndBook($user, null);

        self::assertSame(8, $q['effectiveBorrowLimit']);
        self::assertSame(30, $q['effectiveMaxBorrowDays']);
        self::assertSame(8, $q['remainingBorrowSlots']);
    }

    public function testNonNumericSettingValueFallsBackToDefault(): void
    {
        $borrows = $this->createMock(BorrowsRepository::class);
        $borrows->method('countActiveForMember')->willReturn(0);

        $settings = $this->createMock(SettingsRepository::class);
        $settings->method('findOneBy')->willReturnCallback(function (array $criteria): ?Settings {
            $key = $criteria['key'] ?? '';
            if ('default_borrow_limit' === $key) {
                return (new Settings())->setKey($key)->setValue('not-a-number');
            }

            return null;
        });

        $user = $this->createConfiguredMock(Users::class, [
            'getId' => 1,
            'getBorrowLimit' => null,
        ]);

        $presenter = new BorrowQuotaPresenter($borrows, $settings);
        $q = $presenter->forMemberAndBook($user, null);

        self::assertSame(5, $q['effectiveBorrowLimit']);
    }

    public function testRemainingSlotsNeverNegative(): void
    {
        $borrows = $this->createMock(BorrowsRepository::class);
        $borrows->method('countActiveForMember')->willReturn(10);

        $settings = $this->createMock(SettingsRepository::class);
        $settings->method('findOneBy')->willReturn(null);

        $user = $this->createConfiguredMock(Users::class, [
            'getId' => 1,
            'getBorrowLimit' => 5,
        ]);

        $presenter = new BorrowQuotaPresenter($borrows, $settings);
        $q = $presenter->forMemberAndBook($user, null);

        self::assertSame(0, $q['remainingBorrowSlots']);
    }
}
