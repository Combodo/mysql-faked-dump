<?php
/**
 * Created by Bruno DA SILVA, working for Combodo
 * Date: 16/10/18
 * Time: 11:09
 */

namespace App\Faker;


use Faker\Generator as FakerGenerator;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class FakerAccessor
{
    /** @var FakerGenerator */
    private $fakerGenerator;
    /** @var PropertyAccessor */
    private $propertyAccessor;


    public function __construct(?FakerGenerator $fakerGenerator, ?PropertyAccessor $propertyAccessor)
    {
        $this->fakerGenerator = $fakerGenerator;
        if (! $this->fakerGenerator instanceof FakerGenerator) {
            $this->fakerGenerator = \Faker\Factory::create();
        }

        $this->propertyAccessor = $propertyAccessor;
        if (! $this->propertyAccessor instanceof PropertyAccessor) {
            $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        }
    }

    public function get($propertyPath)
    {
        return $this->propertyAccessor->getValue($this->fakerGenerator, $propertyPath);
    }
}