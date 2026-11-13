<?php

declare(strict_types=1);

namespace Igne\Permissions\Tests;

use Igne\Permissions\Exceptions\PermissionException;
use Igne\Permissions\SubjectDiscovery;
use Igne\Permissions\Tests\Fixtures\Discovery\DiscoveredModel;
use Igne\Permissions\Tests\Fixtures\Discovery\DiscoveredPermission;
use Igne\Permissions\Tests\Fixtures\Discovery\ExtendsDiscoveredBase;
use Igne\Permissions\Tests\Fixtures\Discovery\Nested\ImplementsDiscoveredContract;
use Igne\Permissions\Tests\Fixtures\TestExportReports;
use Illuminate\Support\Facades\Config;

final class SubjectDiscoveryTest extends PermissionTestCase
{
    public function test_it_finds_every_concrete_subject_and_permission_enum(): void
    {
        $this->assertSame([
            DiscoveredModel::class,
            DiscoveredPermission::class,
            ExtendsDiscoveredBase::class,
            ImplementsDiscoveredContract::class,
        ], (new SubjectDiscovery)->within([$this->fixtures()]));
    }

    public function test_it_never_loads_a_class_that_does_not_mention_a_subject_contract(): void
    {
        (new SubjectDiscovery)->within([$this->fixtures()]);

        $this->assertFalse(class_exists('Igne\Permissions\Tests\Fixtures\Discovery\UnrelatedService', false));
    }

    public function test_it_scans_only_the_given_folders(): void
    {
        $this->assertSame([], (new SubjectDiscovery)->within([]));
    }

    /**
     * The interface that names the contract lives outside the scanned folder, so nothing in the
     * folder mentions a marker. Such a class names the contract itself or is listed in subjects.
     */
    public function test_a_subject_whose_contract_is_named_only_outside_the_folders_is_not_found(): void
    {
        $this->assertSame([], (new SubjectDiscovery)->within([$this->fixtures().'/Nested']));
    }

    public function test_a_folder_that_does_not_exist_throws(): void
    {
        $this->expectException(PermissionException::class);

        (new SubjectDiscovery)->within([$this->fixtures().'/Missing']);
    }

    public function test_discovered_and_listed_subjects_build_the_names(): void
    {
        Config::set('permissions.discover', [$this->fixtures()]);
        Config::set('permissions.subjects', [TestExportReports::class]);

        $this->assertSame([
            'discoveredModel',
            'setupTwoFactor',
            'extendsDiscoveredBase',
            'implementsDiscoveredContract',
            'exportReports',
        ], permissions()->all());
    }

    public function test_a_subject_listed_and_discovered_counts_once(): void
    {
        Config::set('permissions.discover', [$this->fixtures()]);
        Config::set('permissions.subjects', [DiscoveredModel::class]);

        $this->assertSame(['discoveredModel'], permissions()->only(DiscoveredModel::class)->names());
        $this->assertCount(4, permissions()->all());
    }

    private function fixtures(): string
    {
        return __DIR__.'/Fixtures/Discovery';
    }
}
