<?php
    namespace Controllers;

    class ProductController
    {
        private $settings;

        public function __construct($rdb)
        {
            $this->settings = $rdb->get('settings');
        }

        public function __invoke($request, $response, $args)
        {
            $params = $request->getParams();

            // Get controller action
            $path = explode('/', trim($request->getUri()->getPath(), '/'));

            switch (array_pop($path))
            {
                case 'delete-picture':
                    $product = \Models\Product::fetchOne([
                        'id' => $params['productId'],
                        'isDeleted' => [
                            '$ne' => true
                        ]
                    ]);

                    if (empty($product)) {
                        return $response->withStatus(400)->write(
                            json_encode([ 'error' => 'Товар не найден' ])
                        );
                    }

                    $picture = \Models\Media::fetchOne([
                        'id' => $params['id'],
                        /*'isDeleted' => [
                            '$ne' => true
                        ]*/
                    ]);

                    if (empty($picture)) {
                        return $response->withStatus(400)->write(
                            json_encode([ 'error' => 'Изображение не найдено' ])
                        );
                    }

                    // Mark picture as deleted
                    $picture->isDeleted = true;
                    $picture->save();

                    // Remove pictureId from brand pictures list
                    $product->pictures = array_values(array_filter(
                        $product->pictures,
                        function($pictureId) use ($picture) {
                            return $pictureId !== $picture->id;
                        }
                    ));

                    // If active brand picture deleted
                    if ($product->pictureId === $picture->id)
                    {
                        $product->pictureId = '';
                    }

                    // Update brand settings
                    $product->save();

                    // Get picture path
                    $picturePath = $this->settings['files']['upload']['directory'] . '/'
                      . $picture->path . '/' . $picture->name;

                    // Delete picture
                    if (unlink($picturePath))
                    {
                        return $response->write(
                            json_encode([ 'success' => true ])
                        );
                    }
                    else
                    {
                        return $response->withStatus(400)->write(
                            json_encode([ 'error' => 'Изображение не найдено' ])
                        );
                    }
                break;
            }
        }

        public function index($request, $response)
        {
            $params = $request->getParams();

            $query = [
                'isDeleted' => [
                    '$ne' => true
                ],
                'type' => 'final'
            ];

            if (isset($params['filter'])) {
                $query = array_merge($query, $params['filter']);
            }

            $sort = null;

            if (isset($params['sort'])) {
                $sort = array_map('intval', $params['sort']);
            }

            $products = [];

            foreach (\Models\Product::fetchAll($query, $sort) as $product) {
                $products[] = $product->expand()->toArray();
            }

            return $response->write(
                json_encode($products)
            );
        }

        public function bootstrap($request, $response)
        {
            $bootstrap = \Models\Product::fetchOne([
                'isDeleted' => [
                    '$ne' => true,
                ],
                'type' => 'bootstrap'
            ]);

            if (empty($bootstrap)) {
                $bootstrap = \Models\Product::getBootstrap();
                $bootstrap->save();
            }

            return $response->write(
                json_encode($bootstrap->expand()->toArray())
            );
        }

        public function get($request, $response, $args)
        {
            $product = \Models\Product::fetchOne([
                'id' => $args['id'],
                'isDeleted' => [
                    '$ne' => true
                ]
            ]);

            if (empty($product)) {
                return $response->withStatus(404)->write(
                    json_encode([
                        'error' => 'Товар не найден'
                    ])
                );
            }

            return $response->write(
                json_encode($product->expand()->toArray())
            );
        }

        public function update($request, $response, $args)
        {
            $params = $request->getParams();

            if (empty($params['title']))
            {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Не заполнено название.'
                    ])
                );
            }

            if (empty($params['description']))
            {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Не заполнено описание.'
                    ])
                );
            }

            if (empty($params['brandId']))
            {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Не заполнен брэнд.'
                    ])
                );
            }

            if (empty($params['categoryId']))
            {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Не заполнена категория.'
                    ])
                );
            }

            if (empty($params['pictures']))
            {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Не заполнено изображение.'
                    ])
                );
            }

            if (empty($params['price']))
            {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Не заполнена цена.'
                    ])
                );
            }

            $product = \Models\Product::fetchOne([
                'id' => $args['id'],
                'isDeleted' => [
                    '$ne' => true
                ]
            ]);

            if (empty($product)) {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Продукт не найден'
                    ])
                );
            }

            $product->type = 'final';
            $product->title = $params['title'];
            $product->description = $params['description'];
            $product->isAvailable = filter_var($params['isAvailable'], FILTER_VALIDATE_BOOLEAN);
            $product->isAuction = filter_var($params['isAuction'], FILTER_VALIDATE_BOOLEAN);
            $product->isNovelty = filter_var($params['isNovelty'], FILTER_VALIDATE_BOOLEAN);
            $product->isBestseller = filter_var($params['isBestseller'], FILTER_VALIDATE_BOOLEAN);
            $product->categoryId = $params['categoryId'];
            $product->brandId = $params['brandId'];
            $product->relatedProducts = $params['relatedProducts'] ?? [];
            $product->pictures = $params['pictures'] ?? [];
            $product->pictureId = $params['pictureId'];
            $product->price = filter_var($params['price'], FILTER_VALIDATE_FLOAT);
            $product->discount = filter_var($params['discount'], FILTER_VALIDATE_FLOAT);
            $product->discountType = $params['discountType'];
            $product->isDeleted = false;
            $product->dateCreated = time();
            $product->save();

            return $response->write(
                json_encode($product->expand()->toArray())
            );
        }

        public function remove($request, $response, $args)
        {
            $product = \Models\Product::fetchOne([
                'id' => $args['id'],
                'isDeleted' => [
                    '$ne' => true
                ]
            ]);

            if (empty($product)) {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Продукт не найден'
                    ])
                );
            }

            $product->isDeleted = true;
            $product->save();

            return $response->write(
                json_encode([
                    'success' => true
                ])
            );
        }

        public function addPicture($request, $response, $args)
        {
            $params = $request->getParams();

            $product = \Models\Product::fetchOne([
                'id' => $args['id'],
                'isDeleted' => [
                    '$ne' => true
                ]
            ]);

            if (empty($product)) {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Продукт не найден'
                    ])
                );
            }

            if (empty($params['picture']['id'])) {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Изображение не задано'
                    ])
                );
            }

            $pictures = $product->pictures ?? [];
            $pictures[] = $params['picture']['id'];
            $product->pictures = $pictures;
            $product->save();

            return $response->write(
                json_encode($product->expand()->toArray())
            );
        }
        
        public function properties($request, $response) 
        {
            $properties = \Models\ProductProperty::fetchAll([
                'isDeleted' => [
                    '$ne' => true
                ]
            ]);

            return $response->write(
                json_encode($properties->toArray())
            );
        }
        
        public function addProperty($request, $response, $args) 
        {
            $params = $request->getParams();

            if (empty($params['key']))
            {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Не заполнено название свойства.'
                    ])
                );
            }
            
            $property = new \Models\ProductProperty();
            $property->key = $params['key'];
            $property->isDeleted = false;
            $property->save();

            return $response->write(
                json_encode($property->toArray())
            );
        }
        
        public function updateProperty($request, $response, $args) 
        {
            $params = $request->getParams();

            if (empty($params['key'])) {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Не заполнено одно из обязательных полей'
                    ])
                );
            }

            $property = \Models\ProductProperty::fetchOne([
                'id' => $args['id'],
                'isDeleted' => [
                    '$ne' => true
                ]
            ]);

            if (empty($property)) {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Свойство не найдено'
                    ])
                );
            }
            
            $property->key = $params['key'];
            $property->save();

            return $response->write(
                json_encode($property->toArray())
            );
        }
        
        public function removeProperty($request, $response, $args) 
        {
            $property = \Models\ProductProperty::fetchOne([
                'id' => $args['id'],
                'isDeleted' => [
                    '$ne' => true
                ]
            ]);
            
            if (empty($property)) {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Свойство не найдено'
                    ])
                );
            }

            $property->isDeleted = true;
            $property->save();

            return $response->write(
                json_encode([
                    'success' => true
                ])
            );
        }
    }