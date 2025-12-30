<?php

declare(strict_types=1);

namespace Atk4\Data\Ci;

class MatrixBuilder
{
    /**
     * @return list<string>
     */
    protected function fetchGithubTags(string $repo): array
    {
        $cmd = 'git ls-remote --tags https://github.com/' . preg_replace('~[^\w\-/]~', '*', $repo) . '.git';
        exec($cmd, $output, $exitCode);
        assert($exitCode === 0);

        $tags = array_map(static function ($line) {
            $matched = preg_match('~^\w{40}\s+refs/tags/(\S+)$~', $line, $matches);
            assert($matched);

            return $matches[1];
        }, $output);

        natsort($tags);

        return $tags;
    }

    /**
     * @return list<string>
     */
    public function buildImageVersionsMysql(): array
    {
        $tags = $this->fetchGithubTags('mysql/mysql-server');

        $versions = [];
        foreach ($tags as $tag) {
            if (preg_match('~^mysql-(\d+\.\d+\.\d+)$~', $tag, $matches)) {
                $versions[] = $matches[1];
            }
        }

        $publishedVersions = array_filter($versions, static function ($version) {
            return !(
                version_compare($version, '5.5.49') <= 0
                || $version === '5.5.63'
                || (version_compare($version, '5.6') >= 0 && version_compare($version, '5.6.30') <= 0)
                || (version_compare($version, '5.7') >= 0 && version_compare($version, '5.7.12') <= 0)
                || (version_compare($version, '8.0') >= 0 && version_compare($version, '8.0.11') <= 0)
            );
        });

        assert(count($publishedVersions) > 100);

        return $publishedVersions;
    }

    /**
     * @return list<string>
     */
    public function buildImageVersionsMariadb(): array
    {
        $tags = $this->fetchGithubTags('MariaDB/server');

        $versions = [];
        foreach ($tags as $tag) {
            if (preg_match('~^mariadb-(\d+\.\d+\.\d+)$~', $tag, $matches)) {
                $versions[] = $matches[1];
            }
        }

        $publishedVersions = array_filter($versions, static function ($version) {
            return !(
                version_compare($version, '5.5.48') <= 0
                || (version_compare($version, '5.5.65') >= 0 && version_compare($version, '5.5.68') <= 0)
                || (version_compare($version, '10.0') >= 0 && version_compare($version, '10.0.24') <= 0)
                || (version_compare($version, '10.1') >= 0 && version_compare($version, '10.1.13') <= 0)
                || $version === '10.1.27'
                || $version === '10.1.42'
                || (version_compare($version, '10.2') >= 0 && version_compare($version, '10.2.4') <= 0)
                || $version === '10.2.28'
                || $version === '10.2.42'
                || $version === '10.3.19'
                || $version === '10.3.33'
                || $version === '10.4.9'
                || $version === '10.4.23'
                || $version === '10.5.0'
                || $version === '10.5.14'
                || $version === '10.6.6'
                || $version === '10.7.2'
                || (version_compare($version, '10.8') >= 0 && preg_match('~\.[01]$~', $version))
                || $version === '10.11.12'
                || $version === '11.4.6'
            );
        });

        assert(count($publishedVersions) > 100);

        return $publishedVersions;
    }

    /**
     * @return list<string>
     */
    public function buildImages(): array
    {
        return array_merge(
            array_map(static fn ($v) => 'mysql:' . $v, array_filter($this->buildImageVersionsMysql(), static function ($version) {
                return !(
                    version_compare($version, '5.5.51') <= 0
                    || (version_compare($version, '5.6') >= 0 && version_compare($version, '5.6.35') <= 0)
                    || (version_compare($version, '5.7') >= 0 && version_compare($version, '5.7.14') <= 0)
                    || (version_compare($version, '8.0') >= 0 && version_compare($version, '8.0.20') <= 0)
                );
            })),
            array_map(static fn ($v) => 'mariadb:' . $v, array_filter($this->buildImageVersionsMariadb(), static function ($version) {
                return !(
                    version_compare($version, '10.2.21') <= 0
                    || (version_compare($version, '10.3') >= 0 && version_compare($version, '10.3.9') <= 0)
                    || (version_compare($version, '10.4') >= 0 && version_compare($version, '10.4.2') <= 0)
                    || (version_compare($version, '10.5') >= 0 && version_compare($version, '10.5.2') <= 0)
                    || (version_compare($version, '10.6') >= 0 && version_compare($version, '10.6.8') <= 0)
                    || (version_compare($version, '10.7') >= 0 && version_compare($version, '10.7.4') <= 0)
                    || (version_compare($version, '10.8') >= 0 && version_compare($version, '10.8.3') <= 0)
                );
            })),
        );
    }

    /**
     * @param 1|-1 $sign
     */
    protected function incrementImage(string $image, int $sign = 1): string
    {
        $pos = strrpos($image, '.');
        assert($pos !== false);

        return substr($image, 0, $pos + 1) . (substr($image, $pos + 1) + $sign);
    }

    /**
     * @return list<array<string, string>>
     */
    public function buildImagesImportantOnly(): array
    {
        $importantImagePairs = [
            // "JSON_ARRAYAGG" and "JSON_OBJECTAGG" functions added and without "group_concat_max_len" limit
            ['mysql:5.7.21', 'mysql:5.7.22'],

            // workaround bind long string param silently cropped
            // https://bugs.mysql.com/bug.php?id=119444
            // https://jira.mariadb.org/browse/MDEV-38153
            ['mysql:8.0.21', 'mysql:8.0.22'],
            ['mysql:8.0.26', 'mysql:8.0.27'],

            // ??? - SelectTest::testUtf8mb4Support
            ['mysql:8.0.32', 'mysql:8.0.33'],

            // JSON ??? - SelectTest::testFxJsonArray
            ['mysql:5.7.20', 'mysql:5.7.21'],

            // https://jira.mariadb.org/browse/MDEV-27151
            ['mariadb:10.3.36', 'mariadb:10.3.37'],
            ['mariadb:10.4.26', 'mariadb:10.4.27'],
            ['mariadb:10.5.17', 'mariadb:10.5.18'],
            ['mariadb:10.6.9', 'mariadb:10.6.10'],
            ['mariadb:10.7.5', 'mariadb:10.7.6'],
            ['mariadb:10.8.4', 'mariadb:10.8.5'],
            ['mariadb:10.9.2', 'mariadb:10.9.3'],

            // https://jira.mariadb.org/browse/MDEV-24784
            ['mariadb:10.5.23', 'mariadb:10.5.24'],
            ['mariadb:10.6.16', 'mariadb:10.6.17'],
            ['mariadb:10.11.6', 'mariadb:10.11.7'],
            ['mariadb:11.0.4', 'mariadb:11.0.5'],
            ['mariadb:11.1.3', 'mariadb:11.1.4'],
            ['mariadb:11.2.2', 'mariadb:11.2.3'],

            // https://jira.mariadb.org/browse/MDEV-27412
            ['mariadb:10.6.19', 'mariadb:10.6.20'],
            ['mariadb:10.11.9', 'mariadb:10.11.10'],
            ['mariadb:11.2.5', 'mariadb:11.2.6'],
            ['mariadb:11.4.3', 'mariadb:11.4.4'],

            // https://jira.mariadb.org/browse/MDEV-34892
            ['mariadb:10.11.9', 'mariadb:10.11.10'],
            // (2nd value was not released) ['mariadb:11.1.6', 'mariadb:11.1.7'],
            ['mariadb:11.2.5', 'mariadb:11.2.6'],
            ['mariadb:11.4.3', 'mariadb:11.4.4'],
            // (2nd value was not released) ['mariadb:11.5.2', 'mariadb:11.5.3'],

            // https://jira.mariadb.org/browse/MDEV-37428
            ['mariadb:10.11.14', 'mariadb:10.11.15'],
            ['mariadb:11.4.8', 'mariadb:11.4.9'],
            ['mariadb:11.8.3', 'mariadb:11.8.4'],
            // (2nd value was not released) ['mariadb:12.0.2', 'mariadb:12.0.3'],

            // SSH - ??? (MariaDB https://jira.mariadb.org/browse/MDEV-36960 )
            ['mysql:8.0.27', 'mysql:8.0.28'],

            // SSH - MySQL and MariaDB 10.5.6 and lower do not emit an error when "select sleep(10)" is interrupted
            ['mariadb:10.5.6', 'mariadb:10.5.7'],

            // SSH - https://jira.mariadb.org/browse/MDEV-37198
            ['mariadb:10.6.18', 'mariadb:10.6.19'],
            ['mariadb:10.11.8', 'mariadb:10.11.9'],
            ['mariadb:11.1.5', 'mariadb:11.1.6'],
            ['mariadb:11.2.4', 'mariadb:11.2.5'],
            ['mariadb:10.11.14', 'mariadb:10.11.15'],
            ['mariadb:11.4.8', 'mariadb:11.4.9'],
            ['mariadb:11.8.3', 'mariadb:11.8.4'],

            // SSH - https://jira.mariadb.org/browse/MDEV-36959
            ['mariadb:10.11.13', 'mariadb:10.11.14'],
            ['mariadb:11.4.7', 'mariadb:11.4.8'],
            ['mariadb:11.8.2', 'mariadb:11.8.3'],

            // SSH - ??? - Test::testIssueTransactionTemporaryTurnedOffAfterLockInShareMode
            ['mariadb:10.11.13', 'mariadb:10.11.14'],
            ['mariadb:11.4.4', 'mariadb:11.4.5'],

            // SSH - ??? - Test::testSelectForUpdateWithNonEqualsCondition
            ['mariadb:11.8.2', 'mariadb:11.8.3'],
        ];

        $allImages = $this->buildImages();

        $allImagesIndex = array_combine(
            $allImages,
            array_fill(0, count($allImages), true)
        );
        $importantImagesIndex = array_combine(
            array_merge(...$importantImagePairs),
            array_fill(0, count($importantImagePairs) * 2, true)
        );

        foreach ($importantImagePairs as $pair) {
            assert(array_keys($pair) === [0, 1]);
            assert($this->incrementImage($pair[0]) === $pair[1]);
            assert(isset($allImagesIndex[$pair[0]]));
            assert(isset($allImagesIndex[$pair[1]]));
        }

        $res = [];
        foreach ($allImages as $image) {
            if (
                !isset($allImagesIndex[$this->incrementImage($image, -1)])
                || !isset($allImagesIndex[$this->incrementImage($image)])
                || isset($importantImagesIndex[$image])
                || isset($importantImagesIndex[$this->incrementImage($image, -1)])
                || isset($importantImagesIndex[$this->incrementImage($image)])
            ) {
                $res[] = $image;
            }
        }

        return $res;
    }

    /**
     * @return list<array<string, string>>
     */
    public function buildMatrix(): array
    {
        return ['mysql-image' => $this->buildImagesImportantOnly()];
    }

    public function outputMatrix(): void
    {
        $matrix = $this->buildMatrix();

        fwrite(\STDERR, print_r($matrix, true));

        echo json_encode($matrix, \JSON_THROW_ON_ERROR) . "\n";
    }
}

$matrixBuilder = new MatrixBuilder();
$matrixBuilder->outputMatrix();
