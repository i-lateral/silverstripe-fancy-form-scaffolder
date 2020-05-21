# SilverStripe Fancy Form Scaffolder

The standard CMS Form Scaffolder does a good job of automatically creating forms to edit records in
the SilverStripe admin, but if you want to create complex form layouts (adding fields to field groups,
composite fields, custom tabs, etc) then you will have to spend a lot of time editing `getCMSFields`.

This module allows scaffolding of complex form layouts in the SilverStripe CMS via YML config. You can defined
tabs, field types, field groupings and assotiations via some arrays in configuration.

## Installing

You can install via composer:

    composer require i-lateral/silverstripe-fancy-form-scaffolder

## Usage

Once installed, simply add a `cms_fields` config variable to your `DataObject` and `FormScaffolder` will build all
CMS forms (loaded using Form Scaffolding) for that object using the FormScaffolder.

### Example Config

```
class Product extends DataObject
{
    private static $db = [
        'Name' => 'Varchar',
        'StockID' => 'Varchar',
        'Description' => 'HTMLText',
        'Price' => 'Currency',
        'ItemsPerCarton' => 'Int'
    ];

    private static $has_one = [
        'Supplier' => Factory::class
    ];

    private static $many_many = [
        'Categories' => Category::class
    ];

    private static $cms_fields = [
        'Root.Main' => [
            'h2' => 'Product Details',
            'fields' => [
                \SilverStripe\Forms\FieldGroup::class => [
                    'name' => 'Title/Stock ID',
                    'fields' => [
                        'Title',
                        'StockID'
                    ]
                ],
                'Description',
                'SilverStripe\Forms\CompositeField' => [
                    'name' => 'Price/Cartons',
                    'fields' => [
                        'BasePrice',
                        'ItemsPerCarton',
                        'SupplierID'
                    ]
                ]
            ]
        ],
        'Root.Components' => [
            'fields' => [
                'Additions'
            ]
        ]
    ];
}
```