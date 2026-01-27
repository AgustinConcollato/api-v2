<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Admin',
        //     'email' => 'bazarshopmayorista@gmail.com',
        //     'password' => Hash::make('1872fa43')
        // ]);


        /////////////////////////////////////////


        // DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // // 2. TRUNCAR LA TABLA DE CATEGORÍAS
        // // Esto vacía la tabla de forma segura, incluso con referencias externas.
        // DB::table('categories')->truncate();

        // // 3. REACTIVAR VERIFICACIONES DE CLAVES FORÁNEAS
        // DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // $parentCategories = [];

        // $nestedData = [
        //     'Cocina' => [
        //         'Aluminio y acero',
        //         'Melamina',
        //         'Teflón',
        //         'Cerámica',
        //         'Herméticos',
        //         'Platos y compoteras',
        //         'Vasos y copas',
        //         'Jarras, jarros y tazas',
        //         'Cubiertos',
        //         'Utensilios',
        //         'Bandejas, bowls y ensaladeras',
        //         'Botellas y bidones',
        //         'Tablas',
        //         'Artículos de asador',
        //         'Secaplatos y coladores',
        //         'Repostería',
        //         'Electrodomésticos',
        //         'Infantiles',
        //         'Rigolleau',
        //         'Carol',
        //         'Tramontina'
        //     ],
        //     'Regalería' => [
        //         'Decoración',
        //         'Portarretratos',
        //         'Lámparas, velas y sahumerios',
        //         'Flores y floreros',
        //         'Bolsos, billeteras y neceseres',
        //         'Relojes'
        //     ],
        //     // ... (Resto de tus categorías principales) ...
        //     'Juguetería' => ['Juegos de mesa', 'Didácticos', 'Verano', 'Vehículos', 'Muñecas/os', 'Animales', 'Musicales', 'Pelotas', 'Bebé', 'Nena', 'Nene', 'Superhéroes'],
        //     'Mates y Termos' => ['Mates', 'Termos', 'Equipos de mate', 'Bombillas', 'Vertedores', 'Portatermos', 'Repuestos', 'Pavas', 'Lumilagro', 'Marwal'],
        //     'Limpieza y Baño' => ['Alfombras', 'Limpieza casa', 'Limpieza personal', 'Baldes, fuentes y palanganas', 'Ropa', 'Cortinas', 'Baño'],
        //     'Varios' => ['Electrónica', 'Jardinería', 'Librería', 'Invierno', 'Camping', 'Organizadores']
        // ];


        // // 1. CREAR CATEGORÍAS PRINCIPALES
        // foreach ($nestedData as $name => $subcategories) {
        //     $category = Category::create([
        //         'name' => $name,
        //         'slug' => Str::slug($name),
        //         'parent_id' => null,
        //     ]);
        //     $parentCategories[$name] = $category;
        // }


        // // 2. CREAR SUBCATEGORÍAS
        // foreach ($nestedData as $parentName => $subcategories) {
        //     $parentCategory = $parentCategories[$parentName];

        //     foreach ($subcategories as $subName) {
        //         $parentCategory->children()->create([
        //             'name' => $subName,
        //             'slug' => Str::slug($subName),
        //             // 'parent_id' se asigna implícitamente
        //         ]);
        //     }
        // }


        ////////////////////////////


        // $oldProviders = [
        //     [
        //         'id' => '3ba63f69-894a-4a38-9c1a-036809897f5b',
        //         'name' => 'FJF',
        //         'provider_code' => 'PROV-FJF',
        //         'contact_info' => null,
        //     ],
        //     [
        //         'id' => '4c2a3956-0526-40d1-86b0-f0e31e4ff248',
        //         'name' => 'Jossefina',
        //         'provider_code' => 'PROV-JO',
        //         'contact_info' => 'https://www.mayoristajossefina.com.ar/',
        //     ],
        //     [
        //         'id' => '5bfc5e9e-9d27-4d93-93aa-bd933ce96f25',
        //         'name' => 'Alison',
        //         'provider_code' => 'PROV-AL',
        //         'contact_info' => 'https://alisondistribuidora.com.ar/',
        //     ],
        //     [
        //         'id' => '6c9a74bf-a4e5-4942-8318-7b4b73480b70',
        //         'name' => 'Polirrubro Santa Fe',
        //         'provider_code' => 'PROV-PSF',
        //         'contact_info' => null,
        //     ],
        //     [
        //         'id' => '91e5f292-899e-40ad-8c46-90041eb64e7c',
        //         'name' => 'Swing',
        //         'provider_code' => 'PROV-SW',
        //         'contact_info' => null,
        //     ],
        //     [
        //         'id' => '944f6fac-4e5b-4ee6-8c42-a1d0f7810be4',
        //         'name' => 'N1 Express',
        //         'provider_code' => 'PROV-N1EX',
        //         'contact_info' => 'https://www.n1express.com.ar/',
        //     ],
        //     [
        //         'id' => '96b93a59-7ff9-4bbc-9a1c-ad5c190f8c31',
        //         'name' => 'Maju',
        //         'provider_code' => 'PROV-MA',
        //         'contact_info' => 'https://mayoristamaju.com/',
        //     ],
        //     [
        //         'id' => '9f5507c5-d7ba-4545-9d00-8f89454d094a',
        //         'name' => 'Hugo Mates',
        //         'provider_code' => 'PROV-HM',
        //         'contact_info' => null,
        //     ],
        //     [
        //         'id' => 'b2b76533-e13e-4d9d-be36-962b5a3f7726',
        //         'name' => 'Mundo Peluche',
        //         'provider_code' => 'PROV-MUPE',
        //         'contact_info' => 'https://mundopeluche.smarty.com.ar/',
        //     ],
        //     [
        //         'id' => 'bdab4500-08b1-400c-a8fb-2c49a8a4e946',
        //         'name' => 'Mates Fabi',
        //         'provider_code' => 'PROV-MF',
        //         'contact_info' => 'https://matesfabi.com/',

        //     ],
        //     [
        //         'id' => 'c1a5ae94-abf7-49c7-bc93-688e350ae776',
        //         'name' => 'EL Regalo',
        //         'provider_code' => 'PROV-ELRE',
        //         'contact_info' => 'https://pency.app/elregalosrl',
        //     ],
        //     [
        //         'id' => 'd1b0c5a1-d560-4132-9315-b3f992001986',
        //         'name' => 'SF Panda',
        //         'provider_code' => 'PROV-SFPA',
        //         'contact_info' => 'https://sfpanda.com.ar',
        //     ],
        //     [
        //         'id' => 'e642d270-1d4c-473b-a9cb-51e68048fff5',
        //         'name' => 'El Mago',
        //         'provider_code' => 'PROV-ELMA',
        //         'contact_info' => 'https://www.magovirtual.com.ar/',
        //     ],
        //     [
        //         'id' => 'e86f1586-f5c8-44ae-a85b-1cee51cb0cda',
        //         'name' => 'Flash',
        //         'provider_code' => 'PROV-FL',
        //         'contact_info' => 'https://flashmayorista.com.ar/',
        //     ],
        //     [
        //         'id' => 'e92b51ec-c112-410c-9f8a-e839c6790c2b',
        //         'name' => 'Megamix',
        //         'provider_code' => 'PROV-ME',
        //         'contact_info' => 'https://www.megamixdistribuidor.com.ar/',
        //     ]
        // ];

        // $suppliersData = [];

        // foreach ($oldProviders as $provider) {
        //     // 2. Mapear los campos antiguos a los nuevos
        //     $suppliersData[] = [
        //         'id' => $provider['id'],
        //         'name' => $provider['name'],

        //         // Los campos nuevos que no existen se dejan en null
        //         'email' => null,
        //         'phone' => null,
        //         'contact_person' => null,

        //         // Mapeamos 'contact_info' a 'address'
        //         'address' => $provider['contact_info']
        //     ];
        // }

        // // 3. Insertar los datos mapeados en la nueva tabla 'suppliers'
        // // Es más eficiente usar insert() para una inserción masiva.
        // if (!empty($suppliersData)) {
        //     // Desactivar temporalmente la comprobación de eventos de modelo para rendimiento
        //     Supplier::withoutEvents(function () use ($suppliersData) {
        //         DB::table('suppliers')->insert($suppliersData);
        //     });
        //     $this->command->info('✅ Datos de Providers migrados a Suppliers exitosamente: ' . count($suppliersData) . ' registros.');
        // } else {
        //     $this->command->info('No se encontraron datos en la tabla providers para migrar.');
        // }


        ///////////////////////


        // $priceList = [
        //     [
        //         'name' => 'Lista Mayorista',
        //         'is_default' => 0,
        //         'created_at' => Carbon::now(),
        //         'updated_at' => Carbon::now()
        //     ],
        //     [
        //         'name' => 'Lista Minorista',
        //         'is_default' => 1,
        //         'created_at' => Carbon::now(),
        //         'updated_at' => Carbon::now()
        //     ]
        // ];

        // DB::table('price_lists')->insert($priceList);


        ///////////////////////


        // $priceList = [
        //     [
        //         'name' => 'Lista Viaje',
        //         'is_default' => 0,
        //         'created_at' => Carbon::now(),
        //         'updated_at' => Carbon::now()
        //     ]
        // ];

        // DB::table('price_lists')->insert($priceList);


        ////////////////////////


        // DB::statement("
        // INSERT INTO list_product (price_list_id, product_id, price, created_at, updated_at)
        // SELECT 
        // 3, 
        // product_id, 
        // (purchase_price * 1.05) / 0.63, 
        // ?, 
        // ?
        // FROM product_supplier
        // ON DUPLICATE KEY UPDATE 
        // price = (purchase_price * 1.05) / 0.63,
        // updated_at = ?
        // ", [Carbon::now(), Carbon::now(), Carbon::now()]);
        
    }
}
