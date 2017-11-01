<?php
    namespace Controllers;
    
    class ProductController
    {
        private static function expandModel($model) 
        {
             // Expand with related products
            $relatedProducts = [];
            if (count($model->relatedProducts) > 0) {
                foreach ($model->relatedProducts as $relatedProductId) {
                    $relatedProducts[] = \Models\Product::fetchOne([ 
                        'id' => $relatedProductId 
                    ])->toArray();
                }
            }
            $model->relatedProducts = $relatedProducts;
            
            // Expand with pictures
            $pictures = [];
            if (count($model->pictures) > 0) {
                foreach ($model->pictures as $pictureId) {
                    $pictures[] = \Models\Media::fetchOne([ 
                        'id' => $pictureId 
                    ])->toArray();
                }
            }
            $model->pictures = $pictures;
            
            return $model;
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
            
            $products = \Models\Product::fetchAll($query);
            
            $productsExpanded = [];
            
            foreach ($products as $product) {
                $productsExpanded[] = self::expandModel($product)->toArray();
            }
            
            return $response->write(
                json_encode($productsExpanded)
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
                json_encode(self::expandModel($bootstrap)->toArray())
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
                json_encode(self::expandModel($product)->toArray())
            );
        }
        
        public function update($request, $response, $args) 
        {
            $params = $request->getParams();
            
            if (empty($params['title']) || empty($params['description']) || 
                empty($params['pictures']) || empty($params['categories'])) 
            {
                return $response->withStatus(400)->write(
                    json_encode([
                        'error' => 'Не заполнено одно из обязательных полей: название, описание, категории, изображение.'
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
            $product->categories = $params['categories'] ?? [];
            $product->relatedProducts = $params['relatedProducts'] ?? [];
            $product->pictures = $params['pictures'] ?? [];
            $product->pictureId = $params['pictureId'];
            $product->discount = filter_var($params['discount'], FILTER_VALIDATE_FLOAT);
            $product->discountType = $params['discountType'];
            $product->isDeleted = false;
            $product->dateCreated = time();
            $product->save();
            
            return $response->write(
                json_encode(self::expandModel($product)->toArray())
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
                json_encode(self::expandModel($product)->toArray())
            );
        }
    }