
   PASS  Tests\Unit\ExampleTest
  ✓ that true is true                                                                                            0.01s  

   PASS  Tests\Unit\Services\AiMenuServiceFallbackTest
  ✓ gemini 5xx falls back to template                                                                            0.89s  
  ✓ gemini 503 falls back to template                                                                            0.05s  
  ✓ gemini 4xx falls back to template                                                                            0.05s  
  ✓ gemini empty candidates falls back                                                                           0.05s  
  ✓ no api key falls back without http call                                                                      0.05s  
  ✓ fallback result is cached for 24h                                                                            0.06s  
  ✓ fallback with no available products                                                                          0.05s  
  ✓ network exception falls back                                                                                 0.05s  
  ✓ fallback text includes user dietary preference                                                               0.05s  
  ✓ regenerate after 5xx still works                                                                             0.05s  
  ✓ text only interface also falls back                                                                          0.05s  

   PASS  Tests\Unit\Services\AiMenuServiceTest
  ✓ generate rejects when no preferences                                                                         0.05s  
  ✓ generate creates daily menu record                                                                           8.08s  
  ✓ second call returns existing menu                                                                            8.06s  
  ✓ regenerate rate limit 3 per day                                                                              8.07s  

   PASS  Tests\Unit\Services\OrderServiceGuardTest
  ✓ guard i1 rejects out of stock                                                                                0.05s  
  ✓ guard i1 rejects zero quantity                                                                               0.05s  
  ✓ guard p1 rejects amount mismatch                                                                             0.06s  
  ✓ guard p1 rejects no payment                                                                                  0.05s  
  ✓ guard g0 allows admin                                                                                        0.05s  

   PASS  Tests\Unit\Services\OrderServiceIdempotencyTest
  ✓ repeated transition only succeeds once                                                                       0.08s  
  ✓ concurrent transitions under db lock                                                                         0.05s  

   PASS  Tests\Unit\Services\OrderServiceRefundTest
  ✓ paid to refunded succeeds                                                                                    0.05s  
  ✓ processing to refunded succeeds                                                                              0.05s  
  ✓ shipped to refunded succeeds                                                                                 0.05s  
  ✓ delivered to refunded succeeds                                                                               0.06s  
  ✓ pending to refunded is rejected                                                                              0.05s  

   PASS  Tests\Unit\Services\OrderServiceTest
  ✓ pending to paid succeeds with valid payment                                                                  0.05s  
  ✓ paid to processing succeeds                                                                                  0.05s  
  ✓ processing to shipped writes tracking no                                                                     0.05s  
  ✓ shipped to delivered succeeds                                                                                0.05s  
  ✓ pending to shipped is rejected                                                                               0.05s  
  ✓ cancelled is terminal no further transition                                                                  0.05s  
  ✓ delivered to refunded succeeds                                                                               0.06s  
  ✓ transition creates audit log                                                                                 0.05s  
  ✓ guarded other user cannot transition                                                                         0.05s  

   PASS  Tests\Unit\Services\PaymentServiceTest
  ✓ duplicate event id is deduplicated                                                                           0.06s  
  ✓ payment succeeded updates payment record                                                                     0.05s  
  ✓ refund transitions order to refunded                                                                         0.05s  

   PASS  Tests\Unit\Services\SubscriptionServiceTest
  ✓ subscribe creates active subscription                                                                        0.05s  
  ✓ subscribe rejects when already active                                                                        0.05s  
  ✓ cancel subscription succeeds                                                                                 0.05s  
  ✓ double cancel rejected                                                                                       0.05s  

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                                                                0.40s  

   PASS  Tests\Feature\Order\ConcurrentRefundTest
  ✓ two concurrent cancels only one succeeds                                                                     0.08s  
  ✓ high concurrency cancel only one wins                                                                        0.06s  
  ✓ cancel and refund race only one wins                                                                         0.05s  
  ✓ concurrent refund calls only succeeds once                                                                   0.05s  
  ✓ multi order concurrent cancel stock aggregate consistency                                                    0.07s  
  ✓ low stock concurrent create order serialized by lock                                                         0.06s  

   PASS  Tests\Feature\Order\WebhookFlowTest
  ✓ single webhook transitions order to paid                                                                     0.07s  
  ✓ repeated webhook only processes once                                                                         0.17s  
  ✓ payment failed event marks payment as failed                                                                 0.06s  

   PASS  Tests\Feature\Web\CartAuthGuardTest
  ✓ unauthenticated post cart returns 401                                                                        0.07s  
  ✓ unauthenticated get cart returns 401                                                                         0.05s  
  ✓ token required for order create                                                                              0.05s  
  ✓ invalid token returns 401                                                                                    0.06s  
  ✓ cart isolated between users                                                                                  0.08s  

   PASS  Tests\Feature\Web\CheckoutFlowTest
  ✓ checkout with empty cart fails                                                                               0.06s  
  ✓ checkout with out of stock fails                                                                             0.05s  
  ✓ checkout clears cart after order                                                                             0.05s  
  ✓ pay with invalid provider fails                                                                              0.06s  
  ✓ pay with non owner returns 403                                                                               0.06s  

   PASS  Tests\Feature\Web\EndToEndCheckoutTest
  ✓ anonymous user can browse products                                                                           0.06s  
  ✓ authenticated user can add to cart                                                                           0.05s  
  ✓ authenticated user can view cart                                                                             0.05s  
  ✓ authenticated user can update quantity                                                                       0.05s  
  ✓ authenticated user can remove item                                                                           0.05s  
  ✓ checkout creates order with cart items                                                                       0.05s  
  ✓ checkout pay returns redirect url                                                                            0.05s  

  Tests:    71 passed (311 assertions)
  Duration: 29.95s

