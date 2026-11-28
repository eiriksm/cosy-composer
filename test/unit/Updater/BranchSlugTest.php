<?php

namespace eiriksm\CosyComposerTest\unit\Updater;

use eiriksm\CosyComposer\Updater\BaseUpdater;
use PHPUnit\Framework\TestCase;
use Violinist\Slug\Slug;

class BranchSlugTest extends TestCase
{
    public function testPublicRepoWithForkUsesFork() : void
    {
        $branch_slug = $this->getBranchSlug(false, 'fork-user');
        self::assertEquals('fork-user', $branch_slug->getUserName());
        self::assertEquals('testRepo', $branch_slug->getUserRepo());
        self::assertEquals('github.com', $branch_slug->getProvider());
    }

    public function testPrivateRepoUsesUpstream() : void
    {
        $branch_slug = $this->getBranchSlug(true, 'fork-user');
        self::assertEquals('testUser', $branch_slug->getUserName());
    }

    public function testNoForkUserUsesUpstream() : void
    {
        $branch_slug = $this->getBranchSlug(false, null);
        self::assertEquals('testUser', $branch_slug->getUserName());
    }

    private function getBranchSlug(bool $is_private, ?string $fork_user) : Slug
    {
        $updater = $this->getMockForAbstractClass(BaseUpdater::class);
        $updater->setSlug(Slug::createFromUrl('https://github.com/testUser/testRepo'));
        $updater->setIsPrivate($is_private);
        if ($fork_user !== null) {
            $updater->setForkUser($fork_user);
        }
        $method = new \ReflectionMethod(BaseUpdater::class, 'getBranchSlug');
        $method->setAccessible(true);
        return $method->invoke($updater);
    }
}
