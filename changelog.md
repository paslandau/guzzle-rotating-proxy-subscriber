###0.0.5

- Added Travis config

###0.0.4

- Made evaluation function cache-aware. It might happen that a request is answered from cache thus has no proxy set since the before-event was intercepted
before a proxy has been set. This led to an exception because it "shouldn't happen" in a non-cache scenario. Removed this restriction, the method
now simply returns without throwing an exception.

###0.0.2 & 0.0.3

- minor fixes

###0.0.1

- Inital commit