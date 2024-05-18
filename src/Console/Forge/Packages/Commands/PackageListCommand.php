<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Packages\Commands;

use Two\Console\Commands\Command;
use Two\Packages\PackageManager;
use Two\Support\Str;

use Symfony\Component\Console\Input\InputOption;


class PackageListCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'package:list';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'List all Framework Packages';

    /**
     * @var \Two\Packages\PackageManager
     */
    protected $packages;

    /**
     * Les en-têtes de tableau pour la commande.
     *
     * @var array
     */
    protected $headers = ['Package', 'Slug', 'Order', 'Location', 'Type', 'Status'];

    /**
     * Créez une nouvelle instance de commande.
     *
     * @param \Two\Packages\PackageManager $package
     */
    public function __construct(PackageManager $packages)
    {
        parent::__construct();

        $this->packages = $packages;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return mixed
     */
    public function handle()
    {
        $type = $this->option('type');

        if (! is_null($type) && ! in_array($type, array('package', 'module', 'theme'))) {
            return $this->error("Invalid Packages type [$type].");
        }

        $packages = $this->getPackages($type);

        if (empty($packages)) {
            if (! is_null($type)) {
                return $this->error("Your application doesn't have any Packages of type [$type].");
            }

           return $this->error("Your application doesn't have any Packages.");
        }

        $this->displayPackages($packages);
    }

    /**
     * Obtenez tous les forfaits.
     *
     * @return array
     */
    protected function getPackages($type)
    {
        $packages = $this->packages->all();

        if (! is_null($type)) {
            $packages = $packages->where('type', $type);
        }

        $results = array();

        foreach ($packages->sortBy('basename') as $package) {
            $results[] = $this->getPackageInformation($package);
        }

        return array_filter($results);
    }

    /**
     * Renvoie les informations du manifeste du package.
     *
     * @param string $package
     *
     * @return array
     */
    protected function getPackageInformation($package)
    {
        $location = ($package['location'] === 'local') ? 'Local' : 'Vendor';

        $type = Str::title($package['type']);

        if ($this->packages->isEnabled($package['slug'])) {
            $status = 'Enabled';
        } else {
            $status = 'Disabled';
        }

        return array(
            'name'     => $package['name'],
            'slug'     => $package['slug'],
            'order'    => $package['order'],
            'location' => $location,
            'type'     => $type,
            'status'   => $status,
        );
    }

    /**
     * Affichez les informations sur le package sur la console.
     *
     * @param array $packages
     */
    protected function displayPackages(array $packages)
    {
        $this->table($this->headers, $packages);
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('--type', null, InputOption::VALUE_REQUIRED, 'The type of Packages', null),
        );
    }
}
