# Architecture: stomp-php

## Purpose

A PHP client library for the STOMP (Simple Text Oriented Messaging Protocol) protocol, enabling PHP applications to publish and consume messages from message brokers such as ActiveMQ, RabbitMQ, Apollo, and OpenMQ.

## Directory Structure

```
src/
  Client.php              - Low-level STOMP client: connect, send, subscribe, ack, transaction
  Simple_Stomp.php        - Simplified facade over Client for common use cases
  Stateful_Stomp.php      - Stateful client that tracks subscription/transaction state
  Network/
    Connection.php                       - TCP socket connection to the broker
    Observer/
      Connection_Observer.php            - Observer interface for connection events
      Connection_Observer_Collection.php - Fan-out to multiple observers
      Heartbeat_Emitter.php              - Sends STOMP heartbeats on schedule
      Server_Alive_Observer.php          - Detects broker silence (missed heartbeats)
  Transport/
    Frame.php             - STOMP frame value object (command, headers, body)
    Frame_Factory.php     - Creates typed frame objects from raw protocol data
    Parser.php            - Parses raw bytes into Frame objects
    Message.php           - Application message value object
    Map.php               - Message body as a key-value map
    Bytes.php             - Binary-safe message body
  Protocol/
    Protocol.php          - Builds STOMP frames for each protocol command
    Version.php           - STOMP protocol version negotiation
  States/
    Producer_State.php    - State for send-only connections
    Consumer_State.php    - State for subscribe-only connections
    Producer_Transaction_State.php / Consumer_Transaction_State.php
    I_Stateful.php        - State machine interface
  Broker/
    ActiveMq/             - ActiveMQ-specific extensions (durable subscriptions, advisories)
    Apollo/               - Apollo-specific extensions (queue browser)
    RabbitMq/             - RabbitMQ-specific extensions
    OpenMq/               - OpenMQ-specific extensions
  Exception/              - Domain exception hierarchy (ConnectionException, StompException, etc.)
```

## Key Design Decisions

- **Three client abstractions**: `Client` for protocol-level control; `Simple_Stomp` for beginner-friendly API; `Stateful_Stomp` for long-running consumer/producer processes that need state tracking.
- **Observer pattern for connection events**: Heartbeat and liveness monitoring are pluggable observers rather than baked into the connection, making them optional and testable.
- **Broker-specific extensions**: STOMP is a protocol, but each broker has extensions. The `Broker/` namespace provides broker-specific mode classes without coupling the core client.
- **State machine**: `Stateful_Stomp` uses a state pattern to prevent illegal operations (e.g., sending in consumer-only state) and to track active subscriptions across reconnects.

## Extension Points

- Implement `Connection_Observer` to add custom monitoring (e.g., Prometheus metrics).
- Add a new broker namespace under `Broker/` for vendor-specific STOMP extensions.

## Dependency Flow

```
Stateful_Stomp / Simple_Stomp
  └─> Client
        └─> Connection (TCP socket)
              └─> Parser (bytes → Frame)
              └─> Protocol (Frame → bytes)
        └─> Connection_Observer_Collection
              └─> Heartbeat_Emitter, Server_Alive_Observer
```
