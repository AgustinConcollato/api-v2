<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryStructureSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar estructura anterior
        DB::table('category_product')->delete();
        DB::table('category_attribute_options')->delete();
        DB::table('category_attributes')->delete();
        Category::query()->delete();

        // [ 'Root' => [ 'Child' => [ attributes ] ] ]
        // attribute: ['name', 'type' (text|number|select|boolean), 'required', 'options'? (solo select)]
        $tree = [
            'Mates y Termos' => [
                'Mates' => [
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Calabaza', 'Acero', 'Madera', 'Vidrio', 'Algarrobo', 'Plástico', 'PVC']],
                    ['name' => 'Color', 'type' => 'text', 'required' => false],
                    ['name' => 'Capacidad (ml)', 'type' => 'number', 'required' => false],
                    ['name' => 'Marca', 'type' => 'text', 'required' => false],
                ],
                'Bombillas y Filtros' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Pico de loro', 'Resorte', 'Perita', 'Chata', 'Hexagonal', 'Desarmable']],
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Acero inox', 'Alpaca']],
                ],
                'Termos' => [
                    ['name' => 'Capacidad', 'type' => 'select', 'required' => false, 'options' => ['500ml', '750ml', '1L', '1.2L', '1.4L', '1.5L', '1.9L', '2L']],
                    ['name' => 'Color', 'type' => 'text', 'required' => false],
                    ['name' => 'Marca', 'type' => 'text', 'required' => false],
                ],
                'Tapones, Vertedores y Sets Materos' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                ],
            ],

            'Vajilla y Bebidas' => [
                'Botellas de Acero Térmicas' => [
                    ['name' => 'Capacidad', 'type' => 'select', 'required' => false, 'options' => ['400ml', '500ml', '600ml', '650ml', '750ml', '800ml', '1000ml', '1500ml']],
                    ['name' => 'Color', 'type' => 'text', 'required' => false],
                ],
                'Botellas Plásticas y Deportivas' => [
                    ['name' => 'Capacidad', 'type' => 'select', 'required' => false, 'options' => ['400ml', '500ml', '600ml', '750ml', '800ml', '1000ml', '1100ml']],
                    ['name' => 'Diseño', 'type' => 'text', 'required' => false],
                ],
                'Vasos y Copas de Vidrio' => [
                    ['name' => 'Capacidad (ml)', 'type' => 'number', 'required' => false],
                    ['name' => 'Cantidad en pack', 'type' => 'number', 'required' => false],
                ],
                'Tazas y Jarros' => [
                    ['name' => 'Capacidad (ml)', 'type' => 'number', 'required' => false],
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Cerámica', 'Vidrio', 'Acero', 'Aluminio', 'Plástico', 'Enlozado']],
                    ['name' => 'Color / Diseño', 'type' => 'text', 'required' => false],
                ],
                'Jarras' => [
                    ['name' => 'Capacidad (ml)', 'type' => 'number', 'required' => false],
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Vidrio', 'Plástico', 'Acero']],
                ],
                'Vasos Plásticos e Infantiles' => [
                    ['name' => 'Capacidad (ml)', 'type' => 'number', 'required' => false],
                    ['name' => 'Personaje / Diseño', 'type' => 'text', 'required' => false],
                ],
                'Vasos y Vasitos de Acero' => [
                    ['name' => 'Capacidad (ml)', 'type' => 'number', 'required' => false],
                    ['name' => 'Color', 'type' => 'text', 'required' => false],
                ],
            ],

            'Cocina' => [
                'Utensilios de Cocina' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Acero inox', 'Plástico', 'Madera', 'Bamboo', 'Silicona', 'Aluminio']],
                ],
                'Moldes y Horneado' => [
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Teflón', 'Silicona', 'Aluminio', 'Hojalata']],
                    ['name' => 'Tamaño', 'type' => 'text', 'required' => false],
                    ['name' => 'Cavidades', 'type' => 'number', 'required' => false],
                ],
                'Ollas, Sartenes y Asaderas' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Cacerola', 'Sartén', 'Wok', 'Asadera', 'Tartera', 'Flanera', 'Budinera']],
                    ['name' => 'Diámetro (cm)', 'type' => 'number', 'required' => false],
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Aluminio', 'Antiadherente', 'Cerámica', 'Enlozada', 'Acero inox']],
                ],
                'Tablas de Picar y Cubiertos' => [
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Plástico', 'Madera', 'Bamboo', 'Acero inox']],
                    ['name' => 'Tamaño', 'type' => 'text', 'required' => false],
                ],
                'Fuentes y Ensaladeras' => [
                    ['name' => 'Capacidad (L)', 'type' => 'number', 'required' => false],
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Vidrio', 'Plástico', 'Cerámica', 'Acero inox']],
                ],
                'Pequeños Electrodomésticos' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                    ['name' => 'Potencia (W)', 'type' => 'number', 'required' => false],
                ],
            ],

            'Almacenamiento y Organización' => [
                'Herméticos y Contenedores' => [
                    ['name' => 'Capacidad (L)', 'type' => 'number', 'required' => false],
                    ['name' => 'Forma', 'type' => 'select', 'required' => false, 'options' => ['Redondo', 'Cuadrado', 'Rectangular']],
                    ['name' => 'Color / Diseño', 'type' => 'text', 'required' => false],
                ],
                'Canastos y Cestos Organizadores' => [
                    ['name' => 'Capacidad (L)', 'type' => 'number', 'required' => false],
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Ratan', 'Tela', 'Plástico', 'Yute', 'Metal', 'Cromado']],
                ],
                'Cestos de Residuos' => [
                    ['name' => 'Capacidad (L)', 'type' => 'number', 'required' => false],
                ],
                'Baldes y Fuentones' => [
                    ['name' => 'Capacidad (L)', 'type' => 'number', 'required' => false],
                    ['name' => 'Color', 'type' => 'text', 'required' => false],
                ],
                'Tarros y Dispensers' => [
                    ['name' => 'Capacidad', 'type' => 'text', 'required' => false],
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Vidrio', 'Plástico', 'Cerámica', 'PET', 'Metal']],
                ],
            ],

            'Juguetes' => [
                'Pistolas, Lanzadores y Armas de Juguete' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Agua', 'Dardos', 'Bolas', 'Misiles', 'Hidrogel']],
                    ['name' => 'Personaje / Diseño', 'type' => 'text', 'required' => false],
                    ['name' => 'Tamaño (cm)', 'type' => 'number', 'required' => false],
                ],
                'Vehículos' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Auto', 'Camión', 'Tractor', 'Camioneta', 'Jeep', 'Grúa', 'Maquina vial']],
                    ['name' => 'Control', 'type' => 'select', 'required' => false, 'options' => ['Fricción', 'Radio control']],
                    ['name' => 'Tamaño (cm)', 'type' => 'number', 'required' => false],
                ],
                'Muñecas y Figuras' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['TINY', 'Labubu', 'Bebote', 'Dinosaurio', 'Mini', 'Animales']],
                    ['name' => 'Tamaño (cm)', 'type' => 'number', 'required' => false],
                ],
                'Juegos de Mesa y Cartas' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                    ['name' => 'Jugadores', 'type' => 'text', 'required' => false],
                    ['name' => 'Edad mínima', 'type' => 'number', 'required' => false],
                ],
                'Arte, Bijouterie y Maquillaje Infantil' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                    ['name' => 'Personaje / Diseño', 'type' => 'text', 'required' => false],
                ],
                'Burbujeros y Juegos de Exterior' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                    ['name' => 'Personaje / Diseño', 'type' => 'text', 'required' => false],
                ],
                'Aprendizaje y Construcción' => [
                    ['name' => 'Piezas', 'type' => 'number', 'required' => false],
                    ['name' => 'Edad recomendada', 'type' => 'text', 'required' => false],
                ],
                'Rompecabezas' => [
                    ['name' => 'Piezas', 'type' => 'number', 'required' => false],
                    ['name' => 'Tamaño', 'type' => 'text', 'required' => false],
                    ['name' => 'Temática', 'type' => 'text', 'required' => false],
                ],
            ],

            'Librería y Papelería' => [
                'Libros para Colorear y Actividades' => [
                    ['name' => 'Temática / Personaje', 'type' => 'text', 'required' => false],
                    ['name' => 'Páginas', 'type' => 'number', 'required' => false],
                    ['name' => 'Tamaño', 'type' => 'text', 'required' => false],
                ],
                'Cuadernos, Anotadores y Agendas' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Cuaderno', 'Anotador', 'Agenda', 'Diario']],
                    ['name' => 'Tamaño', 'type' => 'text', 'required' => false],
                    ['name' => 'Hojas', 'type' => 'number', 'required' => false],
                    ['name' => 'Diseño', 'type' => 'text', 'required' => false],
                ],
                'Lapiceras, Marcadores y Resaltadores' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                    ['name' => 'Colores', 'type' => 'number', 'required' => false],
                    ['name' => 'Diseño', 'type' => 'text', 'required' => false],
                ],
                'Stickers y Adhesivos' => [
                    ['name' => 'Temática', 'type' => 'text', 'required' => false],
                    ['name' => 'Tamaño', 'type' => 'text', 'required' => false],
                ],
                'Cartucheras y Estuches' => [
                    ['name' => 'Diseño / Personaje', 'type' => 'text', 'required' => false],
                ],
            ],

            'Decoración y Flores' => [
                'Flores y Ramos' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Rosa', 'Margarita', 'Tulipán', 'Peonia', 'Gerbera', 'Crisantemo', 'Frezia', 'Campana', 'Rococo', 'Mixto']],
                    ['name' => 'Color', 'type' => 'text', 'required' => false],
                    ['name' => 'Altura (cm)', 'type' => 'number', 'required' => false],
                ],
                'Floreros, Macetas y Velas' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Florero', 'Maceta', 'Vela', 'Hornito']],
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Vidrio', 'Cerámica', 'Plástico', 'Bamboo']],
                    ['name' => 'Tamaño (cm)', 'type' => 'number', 'required' => false],
                ],
                'Cuadros, Láminas y Portaretratos' => [
                    ['name' => 'Tamaño', 'type' => 'text', 'required' => false],
                    ['name' => 'Temática', 'type' => 'text', 'required' => false],
                ],
                'Lámparas Decorativas' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['LED', 'Sal', 'Dino', 'Capibara', 'Colgante', 'Farol']],
                ],
            ],

            'Accesorios y Moda' => [
                'Bolsos y Mochilas' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Bolso', 'Mochila', 'Morral', 'Neceser', 'Portacosmético']],
                    ['name' => 'Color', 'type' => 'text', 'required' => false],
                    ['name' => 'Material', 'type' => 'text', 'required' => false],
                ],
                'Billeteras y Llaveros' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Hombre', 'Mujer', 'Unisex']],
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Cuero', 'Cuerina', 'Tela']],
                    ['name' => 'Color', 'type' => 'text', 'required' => false],
                ],
                'Indumentaria de Invierno' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Gorro', 'Guante', 'Bufanda', 'Set']],
                    ['name' => 'Talle', 'type' => 'text', 'required' => false],
                    ['name' => 'Color', 'type' => 'text', 'required' => false],
                ],
                'Paraguas' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Mini', 'Largo', 'Golf']],
                    ['name' => 'Color / Diseño', 'type' => 'text', 'required' => false],
                ],
                'Accesorios de Cabello' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Colita', 'Hebilla', 'Broche', 'Set', 'Corona']],
                    ['name' => 'Diseño', 'type' => 'text', 'required' => false],
                ],
                'Cinturones' => [
                    ['name' => 'Talle', 'type' => 'text', 'required' => false],
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Cuero', 'Cuerina']],
                ],
            ],

            'Hogar y Limpieza' => [
                'Baño' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                    ['name' => 'Diseño', 'type' => 'text', 'required' => false],
                ],
                'Textiles del Hogar' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Toalla', 'Toallón', 'Mantel', 'Alfombra', 'Cortina']],
                    ['name' => 'Tamaño', 'type' => 'text', 'required' => false],
                    ['name' => 'Color / Diseño', 'type' => 'text', 'required' => false],
                ],
                'Limpieza' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                ],
                'Percheros, Broches y Ganchos' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                    ['name' => 'Material', 'type' => 'select', 'required' => false, 'options' => ['Plástico', 'Metal', 'Madera', 'Felpa']],
                    ['name' => 'Cantidad', 'type' => 'number', 'required' => false],
                ],
            ],

            'Electrónica y Tecnología' => [
                'Parlantes Bluetooth' => [
                    ['name' => 'Potencia (W)', 'type' => 'number', 'required' => false],
                    ['name' => 'Color', 'type' => 'text', 'required' => false],
                ],
                'Auriculares y Cascos' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Vincha', 'In-ear', 'Over-ear']],
                    ['name' => 'Color', 'type' => 'text', 'required' => false],
                ],
                'Cargadores y Cables' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Micro USB', 'Tipo C', 'Auxiliar 3.5mm', 'Doble USB']],
                    ['name' => 'Amperaje', 'type' => 'text', 'required' => false],
                    ['name' => 'Longitud (m)', 'type' => 'number', 'required' => false],
                ],
                'Relojes' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Despertador', 'Pared']],
                    ['name' => 'Diseño', 'type' => 'text', 'required' => false],
                ],
                'Lámparas LED y Nocturnas' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['LED', 'Sal', 'Nocturna', 'Colgante', 'Solar']],
                ],
                'Electrodomésticos' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                    ['name' => 'Potencia (W)', 'type' => 'number', 'required' => false],
                ],
            ],

            'Deportes, Pileta y Camping' => [
                'Pelotas y Deportes' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Fútbol', 'Voley', 'Goma', 'Saltarina']],
                    ['name' => 'Número / Talle', 'type' => 'text', 'required' => false],
                    ['name' => 'Diseño', 'type' => 'text', 'required' => false],
                ],
                'Pileta e Inflables' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                    ['name' => 'Talle / Tamaño', 'type' => 'text', 'required' => false],
                ],
                'Camping' => [
                    ['name' => 'Tipo', 'type' => 'text', 'required' => false],
                ],
            ],

            'Ferretería y Seguridad' => [
                'Candados y Precintos' => [
                    ['name' => 'Tipo', 'type' => 'select', 'required' => false, 'options' => ['Candado', 'Precinto']],
                    ['name' => 'Medida', 'type' => 'text', 'required' => false],
                ],
            ],
        ];

        foreach ($tree as $rootName => $children) {
            $root = Category::create([
                'name' => $rootName,
                'slug' => Str::slug($rootName),
                'parent_id' => null,
            ]);

            foreach ($children as $childName => $attributes) {
                $child = Category::create([
                    'name' => $childName,
                    'slug' => Str::slug($rootName . '-' . $childName),
                    'parent_id' => $root->id,
                ]);

                foreach ($attributes as $order => $attr) {
                    $categoryAttr = $child->attributes()->create([
                        'name' => $attr['name'],
                        'type' => $attr['type'],
                        'required' => $attr['required'],
                        'sort_order' => $order,
                    ]);

                    if ($attr['type'] === 'select' && !empty($attr['options'])) {
                        foreach ($attr['options'] as $optOrder => $optValue) {
                            $categoryAttr->options()->create([
                                'value' => $optValue,
                                'sort_order' => $optOrder,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
