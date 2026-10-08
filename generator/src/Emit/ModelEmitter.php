<?php

declare(strict_types=1);

namespace Axyr\Productive\Generator\Emit;

use Axyr\Productive\Generator\Ir\Attribute;
use Axyr\Productive\Generator\Ir\AttributeType;
use Axyr\Productive\Generator\Ir\Model;
use Axyr\Productive\Generator\Ir\Relationship;
use Axyr\Productive\Generator\Naming;

final readonly class ModelEmitter
{
    public const NAMESPACE = 'Axyr\\Productive\\Data\\Models';

    /**
     * @param  array<string, string>  $classByType  Generated model classes by JSON:API type; other targets stay generic.
     */
    public function __construct(
        private array $classByType,
    ) {}

    public function emit(Model $model, string $see): string
    {
        $dates = array_filter($model->attributes, fn(Attribute $attribute): bool => in_array($attribute->type, [AttributeType::Date, AttributeType::DateTime], true));
        $imports = ['Axyr\\Productive\\Data\\Attributes', 'Axyr\\Productive\\Data\\Model', ...($dates === [] ? [] : ['DateTimeImmutable'])];

        $body = [
            ...PhpFile::docblock([...self::summary($model->description), '@see ' . $see, '', '@SuppressWarnings("PHPMD.ExcessiveMethodLength")', '@SuppressWarnings("PHPMD.ExcessiveClassComplexity")', '@SuppressWarnings("PHPMD.TooManyFields")']),
            'final readonly class ' . $model->class . ' extends Model',
            '{',
            '    public const string TYPE = ' . Literal::string($model->type) . ';',
            '',
            ...array_merge(...array_map(self::property(...), $model->attributes)),
            '    protected function hydrate(Attributes $attributes): void',
            '    {',
            ...array_map(fn(Attribute $attribute): string => sprintf("        \$this->%s = \$attributes->%s(%s);", $attribute->property, Types::model($attribute)['reader'], Literal::string($attribute->name)), $model->attributes),
            '    }',
            ...array_merge(...array_map($this->accessor(...), $model->relationships)),
            '}',
        ];

        return PhpFile::render(self::NAMESPACE, $imports, $body);
    }

    /**
     * @return list<string>
     */
    private static function summary(string $description): array
    {
        return $description === '' ? [] : [$description, ''];
    }

    /**
     * @return list<string>
     */
    private static function property(Attribute $attribute): array
    {
        $type = Types::model($attribute);
        $doc = array_values(array_filter([$attribute->description, $type['doc'] === null ? '' : '@var ' . $type['doc']]));
        $docblock = count($doc) === 1 ? ['    /** ' . $doc[0] . ' */'] : PhpFile::docblock(count($doc) === 2 ? [$doc[0], '', $doc[1]] : [], '    ');

        return [...$docblock, '    public ' . $type['type'] . ' $' . $attribute->property . ';', ''];
    }

    /**
     * @return list<string>
     */
    private function accessor(Relationship $relationship): array
    {
        $class = $relationship->targetType === null ? 'Model' : $this->classByType[$relationship->targetType] ?? 'Model';
        $method = Naming::camel($relationship->name);
        $name = Literal::string($relationship->name);

        if ($relationship->toMany) {
            return ['', ...PhpFile::docblock(['@return list<' . $class . '>'], '    '), '    public function ' . $method . '(): array', '    {', '        return $this->hasMany(' . $name . ', ' . $class . '::class);', '    }'];
        }

        return ['', '    public function ' . $method . '(): ?' . $class, '    {', '        return $this->belongsTo(' . $name . ', ' . $class . '::class);', '    }'];
    }
}
