# PHP# language specification

PHP# is a language that runs on PHP's engine and calls plain PHP freely. This file is the single source of truth for PHP# syntax and rules.

**No PHP# rule makes code write around how PHP# runs on PHP's engine.** A PHP# developer never needs to know what a PHP# construct compiles to. Plain PHP libraries are called as libraries.

**The correct architecture is the only way to write a PHP# program.** Agents write most code, and they repeat whatever the language allows. So PHP# leaves no shortcut to a worse codebase, such as a service locator, a dependency cycle or a check the compiler can't see. A feature exists only where PHP# has no correct way to do the job already. Plain PHP libraries and frameworks stay usable through one correct way at their boundary.

[decisions/](decisions/) records why each rule was chosen.

- Every rule here traces to the Architect's ruling or to mainstream precedent.
- Only the Architect changes a rule.
- Proposals still waiting for his decision are listed under **Open** in their section.

## 1. Attributes

Attributes use square brackets, with these rules:

- several attributes in one bracket: `[Field("Name"), Searchable]`
- several brackets on one declaration
- positional and named arguments
- an argument is built from constants, as a constant's value is (section 2), or is a `new` of a class whose arguments are constants too, as PHP takes; PHP creates the object each time it reads the attribute, and an argument that runs code is a compile error
- no parentheses when there are no arguments: `[Searchable]`
- explicit targets, which name the part of the declaration a bracket goes on: `type:` on a type, `type:` or `event:` on an event, `method:` on a method, an operator, a named constructor, a lambda, an accessor or an observer, `param:` on a parameter and on the value `set` or `init` takes, `field:` on a field, a constant or an enum case, `property:` and `field:` on a property, and `param:`, `property:` and `field:` on a parameter that declares a property, a constructor's, an event's or a data case's, where an untargeted bracket goes on both the parameter and the property
- an attribute always describes a declaration, so a return value takes none, and `[return: NotNull]` is a parse error
- an attribute is data, built only from constants, so the constructor a PHP# attribute class runs, its own or one it inherits, the defaults of its parameters, and the initial values of the class and of each class it extends have no effect, and one that has an effect, as a plain PHP call whose `extern` line says `uses any` has, is a compile error that names it; so reading the attributes of a PHP# class with `attributes<T>()` is pure (section 25)
- on every declaration: a class, an interface, an enum and its cases, an event, a constant, a field, a property, an interface property, a method, an interface method with or without a default body, an operator, a named constructor, a parameter, a lambda written with parentheses, as in `[Searchable] (int n) => n * 2`, and an accessor, as in `[Searchable] get;`, a static or get-only auto-property's included
- on an `extern` line, whose attributes the plain PHP class, method or function it declares carries as its own, `type:` the one target of a class's line and `method:` of a method's or a function's (section 29)
- on `willSet` and `didSet`, whose attributes go on the `set` they run inside, after its own
- a type parameter takes no attributes, so `<[Marker] T>` is a parse error

<!-- example attribute-arguments: members -->
```csharp
[Field(label: "Name", render: Render.Heading, searchable: true)]
public string name { get; set; } = "";
```

<!-- example attribute-interfaces -->
```csharp
// app/Labeled.sharp
namespace App;

[Searchable]
public interface Labeled                  // compiles: on the interface
{
    [Field(label: "Name", searchable: true)]
    string label();                       // compiles

    [Field(label: "Title")]
    string title() => "Untitled";         // compiles: with a default body too

    [Field(label: "Size")]
    int size { get; }                     // compiles: on its property
}
```

<!-- example attribute-events -->
```csharp
// app/Paid.sharp
namespace App;

[Searchable]
public event Paid(int orderId);           // compiles: on the event
```

<!-- example attribute-constants-and-lambdas: members -->
```csharp
[Field(label: "Limit")]
public const int LIMIT = 3;                                        // compiles: on a constant

public Function<int(int)> twice() => [Searchable] (int n) => n * 2;  // compiles: on a lambda
```

<!-- example attribute-targets: members
private void touch() { }
-->
```csharp
[property: Field(label: "Total")]
public int total { [Searchable] get; [param: Field(label: "Amount")] set; } = 0;  // compiles: Searchable on get, Field on what set takes

public int count { [Searchable] get; } = 0;            // compiles: on the get of a get-only auto-property

public int level { get; set; [Searchable] didSet => this.touch(); } = 0;  // compiles: on the set didSet runs inside

[return: Searchable] public string label() => "";     // parse error
```

<!-- example attribute-type-parameters -->
```csharp
// app/Shelf.sharp
namespace App;

public class Shelf<[Searchable] TItem> { }    // parse error
```

<!-- example attribute-objects -->
```php
#[\Attribute(\Attribute::TARGET_ALL)]
final class Route { public function __construct(public string $path, public array $middleware = []) {} }

final class Throttle { public function __construct(public int $perMinute) {} }

#[\Attribute(\Attribute::TARGET_ALL)]
final class Type { public function __construct(public mixed $of, public bool $nullable = false) {} }
```

<!-- example attribute-objects -->
```csharp
// app/OrdersController.sharp
namespace App;

[Route("/orders", middleware: [new Throttle(perMinute: 60)])]         // compiles: Route receives a Throttle object
public class OrdersController
{
    [Type(of: [new Type(typeof(Order), nullable: true)])]             // compiles: Type receives a list holding one Type object
    public Order? order { get; } = null;

    [Route("/limited", middleware: [new Throttle(this.limit())])]     // compile error: an attribute argument is a constant or a new of constants, and this.limit() runs code
    public void limited() { }

    private int limit() => 60;
}
```

<!-- example attribute-data -->
```csharp
// app/Shop/Sampled.sharp
namespace App.Shop;

[Attribute]
public class Sampled
{
    public Sampled(public int rate { get; } = 1) { }              // compiles: the constructor has no effects
}
```

<!-- example attribute-data -->
```csharp
// app/Shop/Shuffled.sharp
namespace App.Shop;

[Attribute]
public class Shuffled
{
    public Shuffled(Random random) { random.nextInt(1, 6); }      // compile error: an attribute has no effects, and nextInt has the effect Random
}
```

Named arguments use `label: "Name"`, the same "name: value" form used everywhere in the language (section 16). `Label = "Name"` is not allowed.

## 2. Variables

Variables have no `$`. A local is declared with `let`, which can be reassigned, or `const`, which cannot.

<!-- example inferred-locals: statements
private Account account = new Account();
-->
```ts
const currency = this.account.currency;
let total = 0;
```

**A local can also be declared with its type written,** when the right side cannot say it, such as `null`. A typed local can be reassigned like `let`, and `const` can carry a type too.

<!-- example typed-locals: statements alone
private Plan plan() => new Plan();
-->
```csharp
let total = 0;              // type inferred, can be reassigned
Money? total = null;        // type written, can be reassigned
const plan = this.plan();   // type inferred, cannot be reassigned
const Plan plan = …;        // type written, cannot be reassigned
```

**A PHP# keyword, such as `let`, `match` or `emit`, names a local only with `@` before it.** Every other word names one as it is, such as `empty`, `list` and `print`:

<!-- example keyword-names: statements -->
```csharp
const list = [1, 2, 3];     // compiles: PHP# has no list keyword
let empty = true;           // compiles
const print = "draft";      // compiles
const @emit = 1;            // compiles: @ makes a keyword a name
const match = 1;            // compile error: match is a keyword; write @match
```

**`@this` is a name like any other,** and a bare `this` stays the object the method runs on. A name before `:` is never the object, so the named argument `this:` passes the parameter `@this`, as `class:` passes `@class`. PHP# has no other `@`: a call's failure is the value it returns (section 23).

**Any word, PHP# keywords included, names a method or a property with no mark,** because a member's name is written only after `.` or where it is declared. Locals, parameters, `catch` variables and pattern variables take a keyword's name with `@`, so a plain PHP method's `$event` parameter is `@event` in PHP#:

<!-- example keyword-members -->
```csharp
// app/Rule.sharp
namespace App;

public class Rule
{
    public bool match(string text) => text != "";   // compiles: a keyword names a method
    public string event { get; } = "paid";          // compiles: and a property
}
```

<!-- example keyword-members: statements
Rule rule = new Rule();
-->
```csharp
rule.match("paid");                                 // true
rule.event;                                         // "paid"
const match = "paid";                               // compile error: match is a keyword; write @match
const @event = "paid";                              // compiles
```

**PHP# keywords are lowercase only.** `Self` (section 25) is the one capitalized keyword. A capitalized keyword is an ordinary name, so `class Event` and `class Struct` are valid. Where no such name exists, as for `TRUE` or `Return`, the error suggests the lowercase keyword:

<!-- example keyword-case -->
```csharp
// app/Struct.sharp
namespace App;

public class Struct { … }       // compiles: struct is a keyword, and Struct is a name
```

<!-- example keyword-case: statements -->
```csharp
const paid = true;              // compiles
const shipped = TRUE;           // compile error: no name TRUE exists; did you mean true?
Return;                         // compile error: no name Return exists; did you mean the keyword return?
```

- In a class, `const` declares a constant. It is never reassigned, and its value is known before the code runs.
- These are removed:
  - `$$name` variable variables
  - `compact()`
  - `extract()`
  - `global`
  - superglobals such as `$_SERVER` and `$GLOBALS` (section 29)

**A constant's value is any expression built from constants:** literals, constants and enum cases read through their class name, a backed case's `value`, the `name` of a case of an enum whose cases carry no data, list and map literals, an index, a template of strings and numbers, `c ? a : b`, a `match` with constant arms, `(int)`, `(float)` and `(string)`, the operators, and a lambda that captures nothing. Any other expression runs code, as a call, a `new` and a template of an object's `toString()` do, so it is a compile error that names it, and a static field takes its place, since a static field's initial value may call code:

<!-- example constant-values
public static class Rates
{
    public static int current() => 3;
}

public class Money
{
    public int cents { get; }

    public Money(int cents) { this.cents = cents; }
}
-->
```csharp
namespace App.Trials;

public enum Plan : string {
    case Free = "free";
    case Pro = "pro";
}

public class Billing {
    public const int TRIAL_DAYS = 14;
    public const int GRACE_DAYS = Billing.TRIAL_DAYS > 7 ? 3 : 1;          // compiles: ? : on constants
    public const List<string> PLANS = ["free", "pro"];
    public const string FIRST_PLAN = Billing.PLANS[0];                     // compiles: an index into a constant
    public const string TRIAL_LABEL = `Trial: ${Billing.TRIAL_DAYS} days`; // compiles: a template of constants
    public const int PRICE_CENTS = (int)(9.99 * 100);                      // compiles: a cast of a constant
    public const Plan DEFAULT_PLAN = Plan.Free;                            // compiles: a case
    public const string DEFAULT_KEY = Plan.Free.value;                     // compiles: a case's value
    public const Function<int(int)> DOUBLE = (int x) => x * 2;             // compiles: a lambda that captures nothing
    public const string PLAN_LABEL = match (Billing.DEFAULT_PLAN) {        // compiles: a match on constants
        Plan.Free => "free plan",
        Plan.Pro => "paid plan",
    };
    public const int RATE = Rates.current();                               // compile error: a constant's value is known before the code runs, and Rates.current() runs code
    public const Money PRICE = new Money(999);                             // compile error: a constant's value is known before the code runs, and new Money(999) runs code
    private static int rate = Rates.current();                             // compiles: a static field's initial value may call code
}
```

Plain PHP reads the same values, a `List` or `Map` constant as its PHP array (section 12):

<!-- example constant-values: statements -->
```php
App\Trials\Billing::PLANS;                                         // ["free", "pro"]
App\Trials\Billing::DEFAULT_PLAN === App\Trials\Plan::Free;        // true
```
<!-- run constant-values
import App.Trials.Billing;
import App.Trials.Plan;
Billing.GRACE_DAYS;                  // 3
Billing.FIRST_PLAN;                  // "free"
Billing.TRIAL_LABEL;                 // "Trial: 14 days"
Billing.PRICE_CENTS;                 // 999
Billing.DEFAULT_PLAN == Plan.Free;   // true
Billing.DEFAULT_KEY;                 // "free"
(Billing.DOUBLE)(4);                 // 8
Billing.PLAN_LABEL;                  // "free plan"
-->

A case is a constant also when other cases of its enum carry data, and a constant that holds it holds the one object the case is, so it is `==` to the case and `is` matches it:

<!-- example constant-data-cases: statements -->
```csharp
public enum Tier
{
    case Free;
    case Seats(int count);
}

public class Defaults
{
    public const Tier TIER = Tier.Free;                      // compiles: a case of an enum whose other cases carry data
}

Defaults.TIER == Tier.Free;                                  // true
Defaults.TIER is Tier.Free;                                  // true
```

A constant whose value reads its way back to it never gets a value, so it is a compile error that names the constants on the way:

<!-- example constant-cycle -->
```csharp
namespace App.Loops;

public class Loop {
    public const int C = Loop.D + 1;   // compile error: `Loop.C` refers back to itself through `Loop.D`
    public const int D = Loop.C + 1;   // compile error: `Loop.D` refers back to itself through `Loop.C`
}
```

An attribute argument, an enum case's value and a pattern's value take the same forms, and a parameter's default is any expression (section 7). An attribute argument also takes a `new` of a class with such arguments (section 1), and a pattern's value takes no lambda, since a function has no `==` (section 19). A lambda that captures a local or names `this` is a compile error that names what it captures.

## 3. Scope

A name lives from its declaration to the `}` that closes its block.

- Each loop pass gets a fresh binding.
- A closure captures the variable itself, not a copy.
- An inner block cannot redeclare a name an outer block declares, and the checker rejects it before the code runs.
- A variable that `is not` creates stays in scope after an `if` whose block always exits (section 21).

## 4. Member access

Every member access uses `.`. There is no `->` and no `::`.

<!-- example dot-access: statements
User user = new User();
string name = "Spring Sale";
-->
```csharp
user.name
Str.slug(name)
```

A class cannot declare a static member and an instance member with the same name, so `Link.name` always has exactly one meaning.

**A member of the same object is always written with `this.`** That holds for fields, properties and methods alike. A bare name is always a local, a parameter, a class or a constant, so any line read on its own shows whether it touches the object. Static members are written with the class name.

<!-- example this-members: statements in Checkout
public static int maximum { get; set; } = 10;
private int count = 0;
private Planner planner = new Planner();
string id = "order-1";
-->
```csharp
this.count += 1;
const plan = this.planner.plan(id);
Checkout.maximum;
```

**`Class.y` without a call always reads a static value:** a static property, a constant, an enum case, or a static method as a function value (section 14.3). A bare `Order.total` is never a property reference, which is written `nameof(Order.total)` (section 6.3).

## 5. Access modifiers

| Modifier | Who can reach it |
|---|---|
| `private` | this class. This is the default for members outside an interface. |
| `protected` | this class and its subclasses, in any namespace |
| `internal` | this namespace and every namespace below it. This is the default for classes. |
| `public` | everyone |

- **A namespace's inside is its folder.** A file's namespace must match its path, so no file can declare its way into a namespace. Each folder below the project's namespace root names a part of the namespace, so a folder whose name a namespace can't take, such as `it's`, is a compile error that asks to rename it.
- **Tests:** test folders may declare the namespace they test, which gives them `internal` access.
- **A member is never more visible than its class.**
- **Visibility is checked both ways.**
  - Too narrow is a compile error at the caller.
  - Too wide is a compile error in an application, for example a `public` member that nothing outside its namespace uses: "`Cart.recount` is public, and nothing outside `App.Shop` uses it." It runs in a project: a package whose `composer.json` does not say `"type": "library"`, a folder with no `composer.json` above it included, so no line left out turns the rule off. A library's members are used by the projects that install it, which the checker never sees, so it never runs there, nor on the standard library or a package installed in `vendor/`. A constructor is exempt: `new` calls it, so it reaches as far as its class.
  - A `public` member takes the narrowest access level that reaches every use, tried in the order `private`, `protected`, `internal`, and the error names it: "`Report.title` is public, and nothing outside `Report` and its subclasses uses it." with "Declare it `protected`." A member must be visible to each class that overrides it, so an override counts as a use from the overriding class, a subclass in another namespace included, which `protected` reaches and `internal` does not. A subclass outside the namespace and other code of the namespace together need `public`. A `virtual` or `abstract` member is never `private`, since a private member is never replaced, so one only its class uses is `protected`.
  - A `public` type is as wide as its uses, as a member is, so one that nothing outside its namespace uses is too wide, a class, an interface, a struct, an enum and an event alike: `public event Quiet(int id);`, which only `App.Shop` creates, is "`Quiet` is public, and nothing outside `App.Shop` uses it." with "Declare it `internal`." A use of the type or of a member of it counts, an `extends` or an `implements` included, and so do plain PHP calling one of its `[Export]` methods and a framework reading a marked attribute of the type or of one of its members. A module and an enum's case are exempt, since no code names them as types.
  - A use from plain PHP counts as one from PHP#, a callable array such as `[Carts::class, 'show']` included. A member counts as used when it carries an attribute whose class is marked `[DynamicallyAccessed]`, and every public member of a class counts as used when the class carries one: the framework that reads the attribute reaches the member through reflection, as a router reaches a `[Route]` method, which the checker never sees. The mark says that reflection reaches the members it covers. It goes on an attribute class, in its declaration, as `#[\Sharp\DynamicallyAccessed]` in a plain PHP library's source, or on an `extern` line of one (section 29). On a member, or on a class, struct, enum or interface that is no attribute class, it would mark nothing, so it is a compile error that names the fix: "`[DynamicallyAccessed]` marks an attribute class, and `DomainRecord` is not one. Mark the attribute that `DomainRecord` carries." An `[Export]` method is exempt too, since plain PHP calls it through the global function it declares (section 23). Any other attribute, as PHP's `[Deprecated]`, `[Import]` or `[Key]`, says nothing about who uses the member, so the member is checked as one without it is. A public member only a view template uses is `internal`, since the checker never reads a template.
- **A class of an application that nothing uses is a compile error,** as a plugin nothing wires in never runs: "Nothing uses `Ghost`, so it never runs." A class is used when code outside it creates it, binds it in a module, names it as a type, extends or implements it, or names it in `typeof` or an attribute, and a use from plain PHP counts. It runs in a project, as the too-wide error does, so a library's classes are exempt. A class that carries an attribute whose class is marked `[DynamicallyAccessed]`, or has a member that carries one, counts as used, as does a class with a listener, a method written with `on` (section 15), which runs when its event is emitted with nothing wired by hand, and a class with an `[Export]` method, which plain PHP calls through the global function it declares (section 23). A class in a test folder counts as used, since its test runner creates it, as PHPUnit creates a test class. A class a framework finds by scanning folders does not: the application binds it or names it. A module and an enum's case are exempt.
- **A public member of an application names its types.** In a project, the types of a `public` member's property, parameters and result hold no `Any`, `Any?` or `Object` at any depth, so an app's API states every type it takes and gives: `public Map<string, Any> checkoutPayload()` is a compile error, "`Store.checkoutPayload` is public, and a public member of an application names its types: `Any` in `Map<string, Any>`." A type parameter is a named type, so `public T first<T>(List<T> items)` compiles. An interface's members count. An `internal` or private member, an `internal` class's members and a library's are exempt, and an override keeps its parent's types, so an override of a plain PHP method that takes `mixed` takes `Any?`. A value of an unknown shape is named by a struct that parses it (section 10).
- **There is no named reach,** such as `internal(App\Tenant)`. A helper shared by sibling namespaces moves to their parent namespace.

**An interface member is always public, so it takes no access modifier.** Writing one, `public` included, is a compile error:

<!-- example interface-members -->
```csharp
// app/Notifier.sharp
namespace App;

public interface Notifier
{
    string channel { get; }                 // compiles: public, as every interface member is
    void send(string message);              // compiles
    public void flush();                    // compile error: an interface member is always public, so write it with no access modifier
}
```

- **An interface member is as visible as its interface,** so an `internal` interface's members reach only what the interface reaches.
- **Interface members are exempt from the too-wide error,** because they have no narrower form.

## 6. Fields and properties

Fields and properties are separate concepts.

- **A field is storage.** It can only be `private` or `protected`. A field with no modifier is `private`. There are no public fields.
- **A property is the API.** It has `get` and `set` accessors. `get` reads at the property's access level, and `set` reaches the property's namespace at most (section 6.1).
- **A field or a constant declares one name.** `private int first, second;` is a compile error that says to declare each on its own line, so a member's type, initial value and attributes stay in one place.

<!-- example field-and-property: members -->
```csharp
Map<string, Plan> cache = [:];                                  // field
public int views { get; private set; } = 0;                     // auto-property
public string name { get; internal set => field = value.trim(); } = "";
public string slug => Str.slug(this.name);                      // computed
```

**A field or an auto-property can have an initial value,** written after its declaration:

<!-- example initial-values: members -->
```csharp
Map<string, Plan> cache = [:];                                  // field
public int views { get; private set; } = 0;                     // auto-property
public List<Tag> tags { get; set; } = [];
```

Initial values run before the parent's constructor, in the order the members are declared, and can't read `this`.

**No code that can read the object runs before the object is built.** An initial value can't read `this`, it fills the storage without running a `set` body, constant or not, and observers wait until the constructor returns (section 6.2). Only a behavior's `set` processes an instance property's initial value, because a behavior never sees the object (section 6.4). A static's initial value fills its storage whatever its accessors and behaviors.

<!-- example initial-value-storage: members -->
```csharp
public string code { get; set => field = value.trim(); } = "  a  ";            // compiles: code starts as "  a  ", since set doesn't run
public string label { get; set => field = value.trim(); } = " b ".uppercase();  // compiles: a computed initial value fills the storage too
```

**A member has no implicit starting value, `null` included.** A `T?` member writes `= null` like any other starting value, and PHP# has no default filler, such as a zero or an empty value of the type.

**A field or a property with storage and no initial value must be set on every path through the main constructor.** This holds for every one, settable or get-only, nullable or not, as it does for a struct. Otherwise it is a compile error that names both fixes: an initial value, or setting it in the constructor.

<!-- example nullable-members -->
```csharp
// app/Profile.sharp
namespace App;

public class Profile
{
    private int? total = null;               // compiles: starts as null
    private int? count;                      // compile error: give count an initial value, or set it on every path through the constructor
    public string? nickname { get; set; }    // compile error: give nickname an initial value, or set it on every path through the constructor
    public string? note { get; }             // compiles: the constructor sets it once
    public string? memo { get; } = null;     // compiles: the initial value sets it once
    public string? handle { get; }           // compile error: give handle an initial value, or set it on every path through the constructor
    public int level { get; set; }           // compiles: the constructor sets it
    public int rank { get; set; }            // compile error: give rank an initial value, or set it on every path through the constructor

    public Profile(string? note, bool claimed)
    {
        this.note = note;
        this.level = 1;
        if (claimed) { this.handle = "taken"; this.rank = 1; this.count = 1; this.nickname = "new"; }
    }
}
```

**A `late` member may stay unset after the constructor.** It is an instance field or an instance property with storage and no initial value, and the constructor need not set it. A read before its first write throws an `Error` that names it as PHP# writes it, and plain PHP's `ReflectionProperty::isInitialized()` gives false until that write, so a framework that fills it after construction tells a value it loaded from one it never loaded, as a PHPUnit test fills a field in `setUp()` and an ORM fills a row after `new`. A `late` static, a `late` member with an initial value, and a `late` computed or `lazy` property are compile errors.

<!-- example late-members -->
```csharp
// app/Order.sharp
namespace App;

public class Order
{
    public late int id { get; private set; }               // compiles: the framework fills it after new
    public late string number { get; set; }                // compiles
    public late string? note { get; set; }                 // compiles: null is a loaded value, unset is not loaded
    public int attempts { get; set; } = 0;                 // compiles: an ordinary member keeps section 6's rule

    public late string status { get; set; } = "open";      // compile error: a late member takes no initial value
    public static late int count { get; set; }             // compile error: a static is never late
    public late string label => this.number;               // compile error: a computed property has no storage to leave unset

    public string title() => `Order ${this.number}`;        // compiles; throws an Error that names App.Order.number when it is read before it is set
}
```

Plain PHP sees PHP's own uninitialized typed property:

<!-- example late-members: statements -->
```php
$order = new App\Order();
$number = new ReflectionProperty(App\Order::class, 'number');
$number->isInitialized($order);      // false
$number->setValue($order, 'A-12');
$number->isInitialized($order);      // true
$order->title();                     // "Order A-12"
(new App\Order())->title();          // throws: "App.Order.number is read before its first write."
```

<!-- example late-test-fields -->
```csharp
// tests/CartTest.sharp
namespace Tests;

import PHPUnit.Framework.TestCase;

public class CartTest : TestCase
{
    private late List<string> items;                       // compiles: setUp fills it before each test

    protected override void setUp() { this.items = ["tea"]; }
}
```

**A static field or property with storage needs an initial value, whatever its type,** so it holds a value of its type before any code reads it, the static constructor included.

**A static lives as long as the app runs, and its initial value runs once, when the app first uses its class's statics,** so it may be any expression. Under FrankenPHP outside worker mode the app runs for one request. Under a long-running host, such as Octane or FrankenPHP's worker mode, each request sees what the last one left. A host that runs several workers runs one app in each. A get-only static is set only by its initial value and by its class's static constructor. In a class without a static constructor, a throw in an initial value reaches the code that first used the statics and runs nothing again. The static it left unset, and each after it whose initial value runs as code, throws an `Error` when it is read, or written with a compound assignment, `++` or `--`, until a write sets it, in a generic class too.

**Plain PHP reaches a static with an accessor body, a computed body or an observer only through a method,** because PHP has no hooks on a static property. Reading or writing it directly from plain PHP is PHP's access error.

<!-- example static-initial-values: members -->
```csharp
private static int hits = 0;                     // compiles
private static int misses;                       // compile error: give misses an initial value
public static string? region { get; set; } = null;   // compiles: starts as null
public static string? zone { get; set; }         // compile error: give zone an initial value
private static List<int> sizes = [10, 25].map(size => size * 2);   // compiles: runs once, when the app first uses the class's statics
public static int limit { get; } = 10;           // compiles: get-only, set only by its initial value and the static constructor
```

**A static constructor, `static Rates() { … }`, sets up its class's statics.** It runs once, right after the statics' initial values, in the same first use of the class's statics, so it never runs while no code uses one: `new` and a constant read use no static. A use of a derived class's statics, its base class's included, runs the base class's static constructor first, then its own. It may fill several statics, and it may run setup that fills none. It writes its class's get-only statics too, and a lambda in it does not. The app runs it and no code calls it, so it takes no modifier but `static` and no parameters, and a class has at most one. Each type argument of a generic class runs it once, for its own statics (section 11). A long-running host keeps the statics between requests, so it runs the static constructor once for the app.

<!-- example static-constructor -->
```csharp
namespace App.Pricing;

public static class Rates
{
    private static Map<string, int> byCurrency = [:];
    private static int fallback = 100;
    public static string home { get; } = "";

    static Rates()                                       // compiles: runs once, right after the statics' initial values
    {
        Rates.byCurrency["USD"] = 100;
        Rates.byCurrency["EUR"] = 92;
        Rates.fallback = Rates.byCurrency["USD"] ?? 0;
        Rates.home = "USD";                              // compiles: the static constructor sets a get-only static
    }

    public static int of(string currency) => Rates.byCurrency[currency] ?? Rates.fallback;
}
```
<!-- run static-constructor
import App.Pricing.Rates;
Rates.of("EUR");   // 92
Rates.of("GBP");   // 100
Rates.home;        // "USD"
-->

<!-- example static-constructor-limits: members in Ledger -->
```csharp
public static Ledger() { }       // compile error: a static constructor takes no modifier but static
static Ledger(int start) { }     // compile error: a static constructor takes no parameters, and a class has one
```

**A throw in a static constructor makes every later use of its class's statics throw.** With a static constructor, the statics' initial values run as its first part, so a throw in either reaches the code that first used the statics. Every later use, a read or a write, through the class or a class derived from it, throws an `Error` that names the class and the failure, and carries the failure as its previous throwable:

<!-- example static-constructor-failure -->
```csharp
namespace App.Feeds;

import RuntimeException;

public static class Feed
{
    private static int polls = 0;

    static Feed()                                        // compiles
    {
        throw new RuntimeException("offline");
    }

    public static int poll() => Feed.polls;
}
```
<!-- run static-constructor-failure
import App.Feeds.Feed;
Feed.poll();   // throws: "offline"
Feed.poll();   // throws: "The static constructor of App.Feeds.Feed threw RuntimeException: offline"
-->

### 6.1 Property features

A PHP# property has these features:

- auto-properties
- a separate, narrower access level for `set` and `init`
- get-only properties, which are set in the constructor
- `init`, which allows a set only while the object is being created
- computed properties, written `=> expr`
- accessor bodies, which use `field` for the hidden storage and `value` for the incoming value
- `required`, which the checker enforces wherever the object is created
- properties in interfaces, abstract properties, and overrides
- Reflection that tells fields from properties

**Only `set` and `init` take an access level of their own,** narrower than the property's. `get` reads at the property's level, so an access level on it is a compile error. A property read only inside its class is read through a method there.

<!-- example access-on-get: members -->
```csharp
public int views { get; private set; } = 0;     // compiles: set is narrower than the property
public int score { protected get; set; } = 0;   // compile error: only set and init take an access level; read score inside the class through a method
```

**A `set` reaches its own namespace at most,** so code outside a namespace never changes the state of its classes. A `set` with no access level reaches the namespace and every namespace below it, or the property's own level where that is narrower, and `private set` narrows it to the class. `public set` and `protected set` are compile errors. Reads stay at the property's level, and a static property follows the same rule. A write from another namespace is a compile error at the write, which names the namespace, in plain PHP files too, since plain PHP that uses PHP# code follows PHP#'s rules. `init` keeps the property's level, so `new` with an object initializer and `with` set it from anywhere (section 16). The `set` runs as PHP's public set, so reflection's `ReflectionProperty::setValue` still writes it, and the checker holds code to the namespace at compile time:

<!-- example set-reach -->
```csharp
// app/Shop/Payments/Transaction.sharp
namespace App.Shop.Payments;

public class Transaction
{
    public string status { get; set; } = "open";       // compiles: set reaches App.Shop.Payments
    public string note { get; init; } = "";             // compiles: init keeps public
    public int attempts { get; public set; } = 0;       // compile error: a set reaches its own namespace at most
}
```

<!-- example set-reach -->
```csharp
// app/Web/Page.sharp
namespace App.Web;

import App.Shop.Payments.Transaction;

public class Page
{
    internal Transaction show(Transaction transaction)
    {
        transaction.status = "paid";                    // compile error: Transaction.status is set only inside App.Shop.Payments
        return new Transaction { note: "web" };         // compiles: init sets it from anywhere
    }
}
```

**A `get` body may have any effect but never writes `this`.** A read may query the database or read the clock, and it carries the body's effects (section 29). Writing a field of `this`, or calling a method on `this` that writes one, is a compile error that names the write. A `lazy` property still keeps its computed value (section 6.5), because that write is the language's, not the body's.

A getter promises less than a method marked `readonly` (section 13): it may call a plain PHP method, and a PHP# method not marked `readonly` that writes nothing of `this`.

<!-- example getter-writes: members
private int reads = 0;
public int count() { this.reads = this.reads + 1; return this.reads; }
-->
```csharp
public int next => this.reads + 1;                                              // compiles: it reads this
public int bumped { get { this.reads = this.reads + 1; return this.reads; } }  // compile error: a getter never writes this, and bumped writes reads
public int counted => this.count();                                             // compile error: count writes reads, and a getter never writes this
```

**A class overrides a plain PHP parent's property with a property** marked `override`, and it writes the type like every PHP# property. Eloquent's `$table`, `$fillable` and `$timestamps` are overridden this way:

<!-- example plain-php-property-override: alone -->
```csharp
namespace App.Store;

import Illuminate.Database.Eloquent.Model;

public class Order : Model
{
    protected override string? table { get; set; } = "orders";                    // compiles: fits Model's @var string|null
    protected override List<string> fillable { get; set; } = ["number", "total"]; // compiles
    public override List<string> with { get; set; } = ["customer"];               // compiles: an override may widen protected to public
    public override bool timestamps { get; set; } = false;                        // compiles

    protected override table { get; set; } = "orders";                            // compile error: write the type
    protected override string? table { get; set; } = 5;                           // compile error: 5 is not string?
    protected override int timestamps { get; set; } = 0;                          // compile error: Model's timestamps is bool
    protected override List<string> fillable { get; set; } = this.columns();      // compile error: an initial value can't read this
    public override bool timestamps = false;                                      // compile error: PHP# has no public fields; write a property
    protected string? table { get; set; } = "orders";                             // compile error: Model declares table; write override
    protected override string? table { get; } = "orders";                         // compile error: Model's own code writes table, so its override has set
    public override bool timestamps { get; private set; } = false;                // compile error: timestamps is set at public in Model
}
```

- **The written type must fit the parent's.** When PHP declares the parent's property with no type, the written type must be assignable to the parent's `@var` type, or to anything when there is no `@var`. The engine drops the written type when the class links, because PHP refuses a typed redeclaration there. When PHP declares the parent's property with a type, the written type must be assignable to it, as `string` is to `string?`. The property keeps the parent's type when the class links, and each write is checked against the written type, so a `null` the parent's code writes to a `string` override is a `TypeError`.
- **The access level is written, and is the parent's or wider,** as PHP lets an override widen it: `protected` in `Model` may be `public`, as `with` is above, and `public` stays `public`, as `timestamps` does. The override's `set` keeps the parent's, since PHP refuses to narrow it, so code of any namespace writes it, and `timestamps { get; private set; }` is an error.
- **The override has `set`.** The parent's own code writes the property at any time, as Eloquent's `setTable` writes `table`, so a get-only override or one with `init` is an error. The override may give `get` and `set` bodies.
- **The initial value runs before the parent's constructor and can't read `this`** (section 6), so a parent's constructor that reads the property, as Eloquent's does, sees it.
- **A PHP# parent's `protected` field is overridden the same way,** by a property measured against that field. A PHP# property is closed unless it is `virtual` or `abstract`, as a method is (section 22), and a field has no `virtual`, so a `protected` field is always open.

### 6.2 Change observers

`willSet` runs before a write, and `didSet` runs after it.

**Observers run only once the object is built,** on every write after its constructor and any object initializer have finished, and also when the value is unchanged. The initial value runs none, and neither does a write while a constructor, a named constructor or an object initializer runs, a method it calls included. `set` and `init` bodies still run for those writes. A `with` copy is a new object being built, so the values it is written with run none either. A static's initial value runs none, and every later write does. Both observers see `value`, the written value, and `field`, the stored one: `willSet` reads the value the write replaces, and `didSet` the value it stored. Beside a `set` body, the order is `willSet`, the `set` body, then `didSet`. An observer runs inside `set`, so a property with one needs `set`.

**`willSet` cannot change or stop the write.** A `return` in it ends `willSet`, and the write still runs, so it returns no value, and a `return` that would leave a `finally` block is a compile error, as is each jump out of one. Writing `value` in it is a compile error, and so is returning in a `set` body before `didSet`:

<!-- example change-observers: members
private void touch() { }
-->
```csharp
public string name { get; set; didSet => this.touch(); } = "";    // compiles: didSet runs on each write once the object is built
public int level { get; set; willSet { if (field != value) { this.touch(); } } } = 0;   // compiles: field is the value the write replaces
public int limit { get; set; willSet { if (field == value) { return; } this.touch(); } } = 0;   // compiles: return ends willSet, and the write runs
public int total { get; set; willSet { value = 0; } } = 0;        // compile error: willSet cannot change value
public int count { get; didSet => this.touch(); } = 0;            // compile error: didSet needs set
```

### 6.3 Typed property references

`nameof(Order.total)` names a property, a field or a method that `Order` declares or inherits, instance or static, readable where `nameof` is written, and gives its name as a string. The reference is typed, and the checker rejects a misspelled or removed member wherever it is used. It sits beside `typeof(Order)` (section 25).

**`nameof(Order.total)` has the type `keyof Order`,** the names of `Order`'s readable members (section 11). A `keyof Order` is a `string`, so it goes wherever a string goes: a `string` parameter, a template, a `Map` key and `==`. A `keyof Order` parameter takes only a member name, so a raw string is refused, and a typo fails the build where the name is written:

<!-- example property-references: statements
Query<Order> query = new Query<Order>();
-->
```csharp
query.orderBy(nameof(Order.total));   // compiles: nameof(Order.total) has type keyof Order
query.orderBy("total");               // compile error: a string isn't a keyof Order
query.orderBy(nameof(Order.totl));    // compile error: Order has no property totl
query.orderBy(Order.total);           // compile error: Order.total reads a static value, and total is an instance property
string message = `${nameof(Order.total)} is invalid`;   // compiles: a keyof Order is a string
```

A bare `Order.total` is never a property reference, so `Class.y` always reads a static value (section 4).

**A method's name passes to a router or an action lookup the same way,** so a rename breaks the build instead of the request:

<!-- example method-names -->
```csharp
// app/OrdersController.sharp
namespace App;

public class OrdersController
{
    public string index() => "orders";
    public string show(int id) => `order ${id}`;
    private string audit() => "audit";
}
```

<!-- example method-names: statements
import Illuminate.Database.Eloquent.Model;
-->
```csharp
nameof(OrdersController.index);    // "index"
nameof(OrdersController.show);     // "show"
nameof(OrdersController.indx);     // compile error: OrdersController declares or inherits no property, field or method indx
nameof(OrdersController.audit);    // compile error: audit is private, so nameof can't name it here
nameof(Model.save);                // "save": a method of a plain PHP class
```

Plain PHP sees the string, so a framework resolves `"index"` as it resolves any action name.

### 6.4 Reusable property behaviors

A clause after the property names its behaviors. It reads as "get/set via Trimmed".

<!-- example property-behaviors: members -->
```csharp
public string name { get; set; } via Trimmed, Tracked = "";
```

A behavior implements `get` and `set` once, as a class that implements the standard library's `Behavior<T>` for the property's type:

<!-- example behavior-class -->
```csharp
namespace App.Text;

import Sharp.Behavior;

public class Trimmed : Behavior<string>
{
    public string get(string value) => value;          // a read returns what is stored
    public string set(string value) => value.trim();   // a write stores the trimmed text
}
```

- A behavior is a class that implements `Behavior<T>` for the property's type, and its constructor takes no arguments. A behavior for another type, an interface, an abstract class, or a constructor that needs an argument is a compile error.
- `via` names a type, so a generic behavior takes its type arguments as every type does (section 11): `via Logged<string>` creates each behavior as `new Logged<string>()`.
- A behavior's `get` and `set` are the property's accessors. Each may have any effect, and a read or write of the property carries it (section 29). `set` may write the behavior's own fields, and `get` writes none, as no `get` body writes `this` (section 6.1).
- `via` goes on an auto-property with `get; set;`, an instance or a static one. A property in an interface or an `abstract` one has no storage to run around, and one with an accessor body already says how it reads and writes, so each is a compile error.
- Each object creates its own behaviors with `new` when it is constructed, before any initial value, and keeps each one in a hidden slot. A copy made with `with`, or with plain PHP's `clone`, creates its own too, so two objects never share a behavior's state. A static property's behaviors belong to its class, which creates them on the property's first read or write and keeps them for the host's app lifetime, as a `static lazy` value is kept (section 6.5). A generic class keeps them for each type argument, as it keeps each static. A static's initial value fills its storage without running `set`, as section 6.2 says, so its behaviors' `set` runs on each later write.
- A write runs each behavior's `set` in the order they are written, then stores the result. A read runs each behavior's `get` the other way, so the first behavior wraps the rest. An instance property's initial value is a write, so it runs through each `set`, which never sees the object (section 6).

### 6.5 `lazy`

`lazy` is a modifier, not a behavior. A lazy property computes its value on the first read, then keeps it and never recomputes it. `static lazy` computes once for the host's app lifetime, the lifetime of a `singleton` (section 32).

<!-- example lazy-property: members
private Planner planner = new Planner();
private int id = 1;
-->
```csharp
public lazy Plan plan => this.planner.plan(this.id);
```

A `lazy` body may have any effect, such as a database query, and reading the property carries the body's effects (section 29). Like every `get` body, it never writes `this` (section 6.1). It may read properties that can change later. The value is computed once and kept, even if those inputs change. A value that must follow its inputs is a plain computed property (`=> expr`).

- **A body that throws keeps nothing,** so the next read runs it again.
- **A copy made with `with` keeps no `lazy` value,** because it is a new value whose inputs may differ. Its first read computes each `lazy` property again.

### 6.6 Indexers

An indexer is a property that takes a parameter: `cart[0]` runs its `get`, and `cart[0] = line` its `set`, whose `value` is the value written. `cart[0] ?? fallback` and `cart[0] += 1` go through `get` and `set` as they do for a property:

<!-- example indexers: members
private List<Line> lines = [];
-->
```csharp
public Line this[int index]
{
    get => this.lines[index];
    set => this.lines[index] = value;
}
```

- **It follows a property's rules.** `get` alone is read-only, so writing it is a compile error, an interface declares one with `get;` and `set;`, and a struct's has no `set`, because a struct never changes (section 10).
- **It takes one parameter,** of any type. An indexer is never `static`, and only a class's `set` takes a modifier, `private`.
- **It runs as PHP's `ArrayAccess`,** so plain PHP's `$cart[0]`, `isset($cart[0])` and `$cart[0] = $line` reach it, each checked against its types. `isset` is true when `get` gives a value that is not null, and `unset($cart[0])` throws `LogicException`, since an indexer has no unset.

## 7. Methods

The return type comes first, and there is no keyword. Every declaration has the same order: modifiers, then the type, then the name.

<!-- example method-declarations: members
private int a = 1;
private int b = 2;
-->
```csharp
public Plan plan(Order order) { … }
public int total() => this.a + this.b;
public T first<T>(List<T> items) { … }
```

**A method can take any number of arguments** with PHP's `...`. The parameter is written `int ...values`, and `values` arrives as a `List<int>`. A call spreads an existing list with `...list`.

<!-- example variadic-parameters: statements in Money
List<int> prices = [900, 2900];
-->
```csharp
public static int sum(int ...values) { … }

Money.sum(1, 2, 3);
Money.sum(...prices);    // spreads an existing list
Math.max(0, ...prices);  // into the standard library too. The 0 gives max a value when prices is empty
```

- `...` is allowed only on the last parameter.
- A spread works the same into PHP# methods and into plain PHP.
- Only a `List` spreads into a call. Spreading a `Map` into a call is a compile error (section 12).
- An override of a method declared with `...` writes that parameter as `string ...parts`, or as the `List` it arrives as, `List<string> parts`. Either way it is the variadic parameter, so a call passes it any number of arguments, and the PHP the override compiles to keeps the `...` PHP requires.

**A spread of unknown length fills only a `...` parameter,** because the checker can't know which other parameters a list of unknown length fills. A list literal's length is known, so its spread fills parameters as its elements written out do. Any other list's spread may start only at the `...` parameter. Anywhere else, a parameter with a default included, it is a compile error that names the parameters it may not fill:

<!-- example spread-of-unknown-length: statements in Pairs
List<int> numbers = [900, 2900];
List<string> tags = ["vip"];
List<Position> positions = [];
-->
```csharp
public static List<T> pair<T>(T a, T b) => [a, b];
public static int tagged(int count, string ...tags) { … }
public static void logSlow(string message, Position caller = Position.current()) { }

Pairs.pair(...numbers);               // compile error: numbers may not fill a and b. A spread of unknown length fills only a ... parameter
Pairs.pair(...[900, 2900]);           // compiles: a list literal's length is known
Pairs.logSlow("slow", ...positions);  // compile error: positions may not fill caller
Pairs.tagged(...tags);                // compile error: tags may not fill count
Pairs.tagged(1, ...tags);             // compiles
Math.max(0, ...numbers);              // compiles: into plain PHP's max(mixed $value, mixed ...$values) too
```

**A class has one method per name, so there is no overloading by argument types.** A second method with the same name is a compile error, whatever it takes. Give each method a name that says what it takes, so a reader of the call knows which one runs:

<!-- example one-method-per-name: members -->
```csharp
public void grantUserAccess(User user) { … }          // compiles
public void grantPageAccess(Page page) { … }          // compiles
public void grant(User user) { … }
public void grant(Page page) { … }                    // compile error: grant is declared twice. Name each by what it takes, as in grantPageAccess
```

**A statement that can never run is a compile error.** A statement after `return`, `throw`, `break`, `continue` or a call that never returns in the same block never runs, and neither does one after an `if` whose every branch ends the block, or after a loop that never ends, such as a `do` whose body ends the block. A loop's condition alone never ends it only when it is the constant `true`, as in `while (true)` and `for (let i = 0; true; i++)`, judged from what it says and not from what a pass narrows it to, so a `do … while (again)` on a `bool` local ends once `again` is false. The error names the line that ends the block, and says to delete the statement or move it above that line. The block of an `if` or a `for … of` whose condition never finishes, as `if (throw new ValueError("empty"))`, never runs either, and the error says to write the condition as its own statement in place of the `if` or the `for`.

<!-- example unreachable-statements: members -->
```csharp
public int total(List<int> prices)
{
    int sum = 0;
    for (const price of prices) {
        if (price < 0) {
            continue;
            sum -= price;                     // compile error: continue ends the block, so this never runs
        }
        sum += price;
    }
    return sum;
    sum = 0;                                  // compile error: return ends the block, so this never runs
}

public int passes(bool again)
{
    int count = 0;
    do {
        count = count + 1;
    } while (again);
    return count;                             // compiles: the loop ends once again is false
}

public int firstStep()
{
    for (let step = 0; true; step++) {
        return step;
    }
}                                             // compiles: the loop never ends on its condition, so no return follows it
```

**A method that never returns declares the return type `never`.** Every path through its body ends in a `throw` or in a call of another `never` method, and a path that reaches the closing brace is a compile error. A call of one ends the block it is in, as `throw` does, so a statement after it never runs, and `input ?? fail(…)` has the type of `input`. An override of a `never` method returns `never` too. `never` is a whole type: no value has it, so it is also a type argument, as in `Result<int, never>`, the result of work that cannot fail, and the type of a parameter, a property or a local, which then holds no value, as `List<never>` holds no element. An expression body returns its value, so a `never` method with one, as `never stop() => 1;`, is a compile error unless the expression never finishes, as `=> throw new ValueError("stopped")` does.

<!-- example never-methods: members -->
```csharp
public static never fail(string reason) => throw new BadPayload(reason);   // compiles

public string name(string? input) => input ?? Example.fail("name");         // compiles: the result is a string

public static Result<int, never> one() => Result.Ok(1);                     // compiles: a result that cannot fail

public static never stop(bool urgent)
{
    if (urgent) {
        throw new BadPayload("stopped");
    }
}                                                                            // compile error: stop is never, and it reaches its closing brace when no throw runs before it
```

**A parameter's default is any expression,** and it runs each time a call leaves the argument out, from PHP# and from plain PHP alike, so `new` gives each call its own object. It runs before the method, so a default that reads `this` or another parameter is a compile error. A call has the effects of each default it leaves out, as if it wrote the default itself, and a call through a class runs the default of the override it reaches (section 29).

<!-- example parameter-defaults -->
```csharp
public class Tickets
{
    private static int issued = 0;

    public static int next()
    {
        Tickets.issued += 1;
        return Tickets.issued;
    }

    public static int take(int number = Tickets.next()) => number;   // compiles: next() runs each time a call leaves number out

    public static int after(int start, int end = start + 1) => end;  // compile error: a default runs before the method, so start is not there yet
}
```
<!-- run parameter-defaults
Tickets.take();     // 1
Tickets.take(10);   // 10
Tickets.take();     // 2
-->

**`callable` is only the type of a parameter an `extern` line states** (section 23), as plain PHP's `add_action` takes its callback. It takes any function value: a method value such as `this.saved`, a lambda, or a value of any `Function<…>` type. Plain PHP receives the closure the value already is, and nothing is checked once it crosses. Plain PHP calls a string as the function it names, and PHP# never does, so a string passed there is a compile error. Anywhere else, the type of a local, a property, a return type, a PHP# method's parameter or a type argument, `callable` is a compile error, because a PHP# function value has the `Function<…>` type that says what it takes and gives (section 14). A call that leaves out a parameter the line gives a default passes the line's default to plain PHP:

<!-- example extern-callable -->
```php
namespace Vendor\Hooks;

function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool { return true; }
```

<!-- example extern-callable -->
```csharp
// app/Stubs/Hooks.sharp
namespace App.Stubs;

import Vendor.Hooks;

extern bool Hooks.add_action(string hookName, callable callback, int priority = 10, int acceptedArgs = 1) uses none;
```

<!-- example extern-callable -->
```csharp
// app/Posts/Saving.sharp
namespace App.Posts;

import Vendor.Hooks;

public class Saving
{
    public void register()
    {
        Hooks.add_action("save_post", this.saved, 10, 3);   // compiles: plain PHP receives this.saved as a closure
        Hooks.add_action("deleted_post", this.deleted);     // compiles: plain PHP receives priority 10 and acceptedArgs 1
        Hooks.add_action("init", "my_function");            // compile error: a string is never a function value
        callable later = this.saved;                         // compile error: callable is only the type of an extern line's parameter
    }

    public void saved(int id, string title, bool update) { }

    public void deleted(int id) { }
}
```

## 8. Functions

There are no top-level functions. Shared code lives in static methods on a class.

**A PHP function is reached through the type it works on,** as a method the standard library declares with an extension (section 26), as in `name.trim()`. Otherwise it goes through a static class of the standard library (section 23), as in `Math.max(a, b)` and `Json.encode(body)`.

<!-- example standard-library-calls -->
```csharp
import Sharp.Encoding.Json;

public class Receipt
{
    public string label(string name) => name.trim();                        // compiles
    public int larger(int a, int b) => Math.max(a, b);                      // compiles
    public string body(Map<string, Any> payload) => Json.encode(payload);   // compiles
    public string raw(string name) => trim(name);                           // compile error: no function trim exists
}
```

- **PHP is reached only through the standard library.** A bare call names a local or nothing, so calling a built-in function by its bare name from `.sharp` code is the compile error a misspelled name gets. The twelve effect classes of section 29 replace the functions for their effects, so `printf`, `time()` and `file_put_contents` are compile errors too.
- **A built-in the standard library has no member for is declared with `[Import]`,** a static method without a body whose PHP# types check each argument and the result (section 23).
- **PHP's constants are reached the same way,** as `Int.MAX` gives the largest `int`. A bare `CURLOPT_TIMEOUT` is a compile error, because a bare uppercase name is always the code's own constant.

**Output and exit go through the standard library.** `echo` and `print` are removed, and output goes through `Console` (section 29). `die` is removed, because `die("…")` prints its message and exits with status 0, which reports success. Exiting goes through `Process`.

<!-- example output-and-exit: statements -->
```csharp
import Sharp.IO.Console;
import Sharp.System.Process;

public class Prune
{
    public Prune(private Console console, private Process process) { }

    public void run(int pruned, bool failed)
    {
        if (failed) {
            this.console.writeError("Prune failed");         // compiles: the message goes to standard error
            this.process.exit(1);                            // compiles: ends the process with status 1
        }
        this.console.writeLine(`Pruned ${pruned} carts`);    // compiles
    }
}

echo "Pruned";                                               // parse error
print "Pruned";                                              // parse error
printf("Pruned");                                            // compile error: no function printf exists
public void stop() { exit(1); }                              // compile error: no function exit exists
public void fail() { die("Prune failed"); }                  // compile error: no function die exists
```

- Exiting skips every `finally` block, as PHP's `exit` does.
- Printing has the effect `Console`, and exiting has the effect `Process` (section 29).

## 9. Constructors

A constructor is named after its class. A parameter with an access modifier declares a member, and the parameter spells out which kind:

- **`private Planner planner`** declares a field.
- **`public Account account { get; }`** declares a property. A `public` parameter without accessor braces is a compile error, just as a public field is.
- **A parameter with no modifier** is an ordinary parameter.

<!-- example parameter-members: members in StoreService -->
```csharp
public StoreService(
    private Planner planner,
    [Field(label: "Account")] public Account account { get; private set; },
) {
    if (!account.active) { throw new InactiveAccount(account); }
}
```

**A member declared on a parameter is a property like one declared in the class body:** its access levels, accessors, accessor bodies, observers, behaviors and attributes, and a default after its accessors and behaviors, which the call may leave out. A default before the accessors, as PHP orders a promoted property, is a compile error that says to write it after them. The argument is a write while the constructor runs, so a `set` body and the behaviors run on it, and on the default too, while the observers wait until the object is built, as section 6.2 says. A parameter without a default is required by every call, so `required` on one is a compile error. A computed property and `lazy` compute their own value, and a parameter receives one, and `static` belongs to no one object, so each goes on a member in the class body:

<!-- example parameter-member-limits: members in Ledger -->
```csharp
public Ledger(
    public string name { get; set => field = value.trim(); } = "",   // compiles: the argument runs through set
    public string slug => this.name.trim(),                          // compile error: a parameter cannot be a computed property
) { }
```

<!-- example optional-parameter-properties -->
```csharp
public class GatewayEvent
{
    public GatewayEvent(
        public string type { get; },                // compiles: a required property
        public string rawBody { get; } = "",        // compiles: an optional property, "" when the call leaves it out
    ) { }
}
```
<!-- run optional-parameter-properties
GatewayEvent paid = new GatewayEvent("charge.succeeded");
paid.rawBody;   // ""
-->

**A constructor marked `required`** is one that `new Self(…)` can call on the class and on every subclass. Section 25 gives its rules.

**A PHP# object is only built through its constructor,** so every object holds what its constructor checks. `new` and `createInstance` (section 25) run it, and so do plain PHP's `new`, `ReflectionClass::newInstance` and `newInstanceArgs`, and a lazy proxy's factory. `unserialize` builds a struct or a class through its `parse`, which runs the constructor (section 10). Each other way plain PHP creates an object throws an `Error` that names the type, "App.Account is built only through its constructor, which this creation doesn't run": `ReflectionClass::newInstanceWithoutConstructor`, `newLazyGhost` and `resetAsLazyGhost`, PDO's `FETCH_CLASS` and every driver's fetch into a class, and SOAP's class map. A plain PHP class that extends a PHP# class is built the same way, through a constructor chain that runs the PHP# constructor, so a test double that skips it, as PHPUnit's `createMock` makes, throws "App.Catalog is built only through its constructor, which this creation doesn't run".

### 9.1 Named constructors

A class has one unnamed main constructor. Any other constructor has a name and passes control to the main one with `: this(…)`. Every creation is written `new Class…`.

<!-- example named-constructors: statements in Artifact
string json = "{}";
-->
```csharp
public Artifact(public Manifest manifest { get; }) { }
public Artifact.fromJson(string json) : this(Manifest.parse(json)) { }

let artifact = new Artifact.fromJson(json);
```

- The dependency container always uses the main constructor (section 32).
- A class has one main constructor, as it has one method per name (section 7).
- A static method that looks up an existing object, such as `Journey.forUuid`, stays a static method.
- A named constructor marked `required`, as in `public required Artifact.fromJson(string json)`, is one every subclass declares, so `new TItem.fromJson(…)` and `new Self.fromJson(…)` can call it (sections 11 and 25).
- An abstract class has no objects of its own, so a named constructor it declares is `required`, the one each subclass declares.

**A subclass's constructor always calls its parent's with `: super(…)`,** as in `public Order(Row row) : super(row) { }`, even when the parent's takes no arguments. A constructor without the call is a compile error:

<!-- example super-call -->
```csharp
// app/Document.sharp
namespace App;

public class Document
{
    public Document() { }
}
```

<!-- example super-call -->
```csharp
// app/Invoice.sharp
namespace App;

public class Invoice : Document
{
    public Invoice(public int number { get; }) : super() { }      // compiles: Document takes no arguments, and the call is still written
}
```

<!-- example super-call -->
```csharp
// app/CreditNote.sharp
namespace App;

public class CreditNote : Document
{
    public CreditNote(public int number { get; }) { }             // compile error: CreditNote extends Document, so write : super()
}
```

**`this` is refused in the arguments of `: super(…)` and `: this(…)`,** because the object doesn't exist until the constructor runs:

<!-- example constructor-call-this: members in Artifact -->
```csharp
public Artifact(public Manifest manifest { get; }) { }
public Artifact.fromJson(string json) : this(Manifest.parse(json)) { }   // compiles
public Artifact.copy() : this(this.manifest) { }                         // compile error: `this` is not available before the constructor runs: pass a parameter instead.
```

**A get-only property is set once.** A named constructor runs on the new object after the main constructor, so it can't set a get-only property again. A property that a named constructor sets again is declared `{ get; init; }` (section 6.1):

<!-- example set-once: members in Money -->
```csharp
public Money(public int amount { get; }, public string currency { get; init; }) { }
public Money.zero(string currency) : this(0, "USD") { this.currency = currency; }   // compiles: currency is init
public Money.free(string currency) : this(1, currency) { this.amount = 0; }         // compile error: amount is get-only, and the main constructor set it
```

## 10. Structs

A `struct` is a value type.

- **A struct never changes.** A method returns a changed copy, usually built with `with`. `point.x = 5` is a compile error that names a method returning a changed copy.
- **A struct has no effects** (section 29). Its methods, `get` bodies and operators reach no database, network, file, clock or event, as a value is the same wherever it is read, so `public int inEuros(Http http) => http.get(url).status;` is a compile error: "`Money.inEuros` has the effect `Http`, and a struct has no effects." A class that takes the value does the work.
- **`readonly struct` is a compile error,** because every struct is already read-only.
- **A struct takes every visibility a class takes,** `public` and `internal` (section 5), and is `internal` when it names neither. An `internal struct` reaches its namespace and every namespace below it, so a module keeps a value type of its own private. Any other modifier, as `abstract` or `final`, is a compile error.
- **`==` compares values.** `===` works on classes only (section 19).
- **A struct cannot inherit or be inherited,** but it can implement interfaces.
- **A struct has no default value.** Every property or field without an initial value must be set when the struct is created, and `= null` makes a nullable one optional.
- **A struct can be generic,** as in `public struct Pair<TFirst, TSecond>`, with the type parameters of section 11.

<!-- example struct-changes -->
```csharp
// app/Point.sharp
namespace App;

public struct Point
{
    public int x { get; init; }
    public int y { get; init; }
    public Point moved(int dx, int dy) => this with { x: this.x + dx, y: this.y + dy };
}
```

<!-- example struct-changes -->
```csharp
// app/Size.sharp
namespace App;

public readonly struct Size { … }     // compile error: every struct is already read-only; write struct
```

<!-- example struct-changes: statements
Point p = new Point { x: 1, y: 2 };
-->
```csharp
Point q = p.moved(1, 0);              // compiles: q is a changed copy, and p is unchanged
p.x = 5;                              // compile error: a struct never changes; return a changed copy from a method, such as moved
Point origin = new Point { x: 0 };    // compile error: y has no initial value, so set it when the Point is created
```

<!-- example internal-struct -->
```csharp
// app/Shop/CheckoutTotals.sharp
namespace App.Shop;

internal struct CheckoutTotals
{
    public CheckoutTotals(public int subtotal { get; }, public int shipping { get; }) { }
}
```

<!-- example internal-struct -->
```csharp
// app/Shop/Checkout.sharp
namespace App.Shop;

public class Checkout
{
    public int total(int subtotal)
    {
        const totals = new CheckoutTotals(subtotal, 499);   // compiles: CheckoutTotals is internal to App.Shop, and this is App.Shop

        return totals.subtotal + totals.shipping;
    }
}
```

<!-- example internal-struct -->
```csharp
// app/Reports/Revenue.sharp
namespace App.Reports;

import App.Shop.CheckoutTotals;                             // compile error: CheckoutTotals is internal to App.Shop, and App.Reports is outside it

public class Revenue { }
```
<!-- run internal-struct
import App.Shop.Checkout;
new Checkout().total(2500);   // 2999
-->

`with` copies an object or a struct and sets the listed properties through their `init` or `set` accessors. The original is unchanged.

**A struct keys a `Map` when every field has `==`, by its whole value with nothing declared, or when it declares `==` with `hash()`, by that pair.** It is the general rule for keys (section 12): a struct whose every field has `==` has `==` and `hash()`, and a struct with a field that has none, such as a function, declares both (section 19). Two structs are one key when they are `==`. A struct gets JSON key text only from its `toString()`, so `Json.encode` of a `Map` keyed by a struct without `toString()` is a compile error, because a JSON object key is text.

**Every struct has a static `parse`,** which takes a `Map<string, Any?>|Object`, reads the `Map`'s keys or the object's public properties and throws one error that lists every bad field, and a static `tryParse`, which gives null instead. The names follow `Int.parse` and `Int.tryParse` (section 24). Every class gets both the same way, and `methods()` (section 25) gives each with that parameter type.

<!-- example struct-parse: statements
Map<string, Any?> payload = ["customerId": "abc"];
-->
```csharp
public struct RenewRequest
{
    public RenewRequest(
        public int customerId { get; },
        [Key("plan_code")] public Plan plan { get; },
        public string? coupon { get; },
    ) { }
}

RenewRequest request = RenewRequest.parse(payload);     // throws: "customerId: expected int, got string 'abc'; plan_code: missing"
RenewRequest? maybe = RenewRequest.tryParse(payload);   // null on any bad field
```

- The keys are the parameter names of the main constructor (section 9.1), then every other stored property, whatever its setter's visibility, so a `private set` property and a machine's (section 20.2) are read and written too. A computed property stores nothing.
- `[Key("plan_code")]` renames the key a parameter reads.
- A nested struct parses the same way.
- A `List` checks each element.
- An enum parses from its value.
- A missing key for a `T?` parameter reads as null.
- A value as text, a class with a static `parse(string)` and a `toString()`, as `Decimal` and `Instant` (section 24), reads through its own `parse`, so `"1.50"` is a `Decimal`. Its error message is the field's problem.
- A field of a union, of classes as `Voucher|Coupon` or of scalars as `(string|int|float|bool)?`, reads as the first member, in the order the field writes them, that reads the value with no problem. A scalar member reads JSON's value as it is and a query's from its text, so `int|string` reads `"3"` from JSON as `"3"` and from a query as `3`.

An object's public properties parse the same way as a `Map`'s keys, so a row object from plain PHP becomes a checked struct:

<!-- example struct-parse-object: statements
Object row = Rows.withId("abc");
-->
```csharp
public struct OrderRow
{
    public OrderRow(public int id { get; }, public string number { get; }) { }
}

OrderRow order = OrderRow.parse(row);   // row is a plain PHP object from a database query; throws: "id: expected int, got string 'abc'"

public class OrderRecord
{
    public OrderRecord(public int id { get; }, public string number { get; }) { }
}

OrderRecord record = OrderRecord.parse(row);   // a class parses the same way, by its main constructor's parameters, then every other stored property; throws: "id: expected int, got string 'abc'"
```

**`Json.encode` writes a struct or a class by the fields its `parse` reads,** so one declaration governs reading and writing. Each field is written under its key, a value as text as its `toString()`, and a field marked `[Key(omitNull: true)]` is left out when it is null. A missing key of such a field reads as null. A main constructor parameter that holds no property can't be written, so `Json.encode` of a type whose `parse` reads one is a compile error that names the parameter: "`Json.encode` writes `Price` from the properties its `parse` reads, and the main constructor parameter `cents` holds no property."

**`serialize` writes a struct or a class as its stored form, the fields `Json.encode` writes, and `unserialize` builds it again through its `parse`,** so the constructor runs on every copy, as a queued job or a cached value is read back. A copy reads back `==` to the original, and data its type no longer reads, as a cached value written before its type changed, throws the error `parse` throws or the constructor's own. `serialize` of a type with no stored form throws an `Error` that names the class and says why: a main constructor parameter that holds no property, as a service a class takes, a field that holds a function, or a `Map` keyed by a type that has no text. A class whose plain PHP parent serializes its objects itself, through `__serialize` and `__unserialize`, as an ORM's model does, keeps the parent's. PHP# code declares none of `__serialize`, `__unserialize`, `__sleep` and `__wakeup`.

**A family is a sealed interface, an abstract class or an enum whose cases carry data, marked `[Tag("type")]`.** Each variant below it, a class, a struct or an enum case, names itself with `[Key("card")]`. The family's `parse` reads the variant whose name the input holds under the tag's key, and `Json.encode` writes a variant with its name under that key, so renaming a class never changes stored data. An interface holds no static method body, so an interface family's `parse` is called from PHP# code. `typeof(Self).withTag(name)` gives the variant's class (section 25).

<!-- example data-in-out -->
```csharp
// app/Payments/PaymentMethod.sharp
namespace App.Payments;

import Sharp.Tag;

[Tag("type")]
public sealed interface PaymentMethod { }
```

<!-- example data-in-out -->
```csharp
// app/Payments/Card.sharp
namespace App.Payments;

import Sharp.Key;

[Key("card")]
public struct Card : PaymentMethod
{
    public Card(public string last4 { get; }) { }
}
```

<!-- example data-in-out -->
```csharp
// app/Payments/Payment.sharp
namespace App.Payments;

import Sharp.Decimal;
import Sharp.Key;

public struct Payment
{
    public Payment(
        [Key("return_url")] public string returnUrl { get; },
        public Decimal amount { get; },
        [Key(omitNull: true)] public string? coupon { get; },
        public PaymentMethod method { get; },
    ) { }
}
```
<!-- run data-in-out
import App.Payments.Card;
import App.Payments.Payment;
import Sharp.Decimal;
import Sharp.Encoding.Json;
Json.encode(new Payment("https://shop.test/done", Decimal.parse("1.50"), null, new Card("4242")));   // "{\"return_url\":\"https://shop.test/done\",\"amount\":\"1.50\",\"method\":{\"type\":\"card\",\"last4\":\"4242\"}}"
-->

- A variant without `[Key]` is a compile error: "`Cash` is a variant of `PaymentMethod` and gives no name for it." A backed machine's case is named by its value instead (section 20.2).
- Two variants of one family with one name are a compile error: "`Debit` and `Card` are both named `card` in `PaymentMethod`."
- A field of a variant read by the tag's key is a compile error: "`type` is read twice: by the tag of `PaymentMethod` and the main constructor parameter `type`."
- A name the family has no variant of is the problem of the tag's key: "type: expected bank or card, got string 'cash'". The names are listed in their order.

## 11. Generics

**Generics are reified.** The running program knows every type argument.

- `page is PaginatedList<Order>` is checked while the code runs.
- A value of the wrong type throws a `TypeError` where it enters PHP# from plain PHP. Section 12 lists where a value enters. An `int` where PHP# expects a `float` does not throw: it becomes a `float` (section 24).

**Each type argument of a generic class has its own static members.** `Counter<Order>.count` is separate from `Counter<User>.count`, and a static member may use the class's type parameters:

<!-- example generic-statics: statements -->
```csharp
public class Counter<T>
{
    public static int count { get; set; } = 0;
    public static List<T> seen { get; set; } = [];      // compiles: a static member may use T
}

Counter<Order>.count += 1;
Counter<User>.count;                                     // 0: Counter<User> has its own count
Counter.count;                                           // compile error: name the type argument, as in Counter<Order>.count
```

A static field names its type arguments: `Counter<Order>.count`. A bare `Counter.count` names none and is a compile error. A static method call fixes them as a generic method call does, below.

**Writing type arguments:**

- **`new` names them, or takes them as a generic method call does,** from its arguments or from the declared type its object goes into: `new PaginatedList<Order>(…)`, `PaginatedList<Order> page = new PaginatedList(rows)` and `new Box(5)`, a `Box<int>`, all compile, and a `new` that nothing fixes a type argument of is a compile error that says to write them. A class value that carries them gives them too, as in `new (pages)()` (section 25). `new Self(…)` names none, because `Self` is the class with its own type parameters.
- **A type names them too:** `PaginatedList<Order> page`. `Class<PaginatedList>` is the one exception: a class type names the class itself.
- **A generic method call fixes them** from the arguments it receives, or from the declared type its result goes into. The declared type reaches the call through each arm of a `match`, through the result of a lambda written for a function type, and through the parameter of an outer generic call that the declared type fixed. An argument fixes a type PHP# writes: `int` for `5`, `List<int>` for `[1, 2]` and the enum for its case, never the literal's own type. A type argument that none of these fixes is a compile error.
- **A static method call of a generic class fixes the class's type arguments the same way,** or names them on the class: `Holder<int> holder = Holder.of(5);`, `const holder = Holder.of(5);` and `Holder<int>.of(5)` all compile, and a bare `Holder.empty()` with no declared type, which nothing fixes, is a compile error.
- **A plain PHP generic class,** declared with PHPDoc's `@template`, names its type arguments in a PHP# type, as in `Collection<Order>`, and `new` names them or takes them as it does for a PHP# class. A bare `Collection items`, or a `new Collection()` that nothing fixes them for, is a compile error that names the type parameters to write. A type parameter with a default may be left out. The checker checks the type arguments, but the object carries none while the code runs, since PHPDoc's types never reach the engine. So a type test tests the class alone: `x is Collection` compiles, and `x is Collection<Order>` is a compile error that says the type argument is unknown while the code runs (section 21).

<!-- example plain-php-generic-types: members -->
```csharp
public void fill(Cell<float> cell) { }                  // compiles: the type names Cell's type argument
public void clear(Cell cell) { }                        // compile error: Cell needs its type argument, as in Cell<float>
public bool holds(Any value) => value is Cell;          // compiles: a type test tests a plain PHP class alone
```

<!-- example generic-method-inference: statements alone
namespace App.Inference;
import App.Order;
Cache cache = new Cache();
-->
```csharp
public class Cache
{
    public T get<T>(string key) { … }
    public T keep<T>(T value) => value;
}

List<Order> orders = cache.get("orders");               // compiles: the declared type fixes T as List<Order>
List<Order> kept = cache.keep(cache.get("orders"));     // compiles: keep's parameter takes List<Order>, which fixes get's T
const orders = cache.get("orders");                     // compile error: nothing fixes T; write cache.get<List<Order>>("orders")
```

<!-- example new-inference: statements alone
namespace App.Inference;
-->
```csharp
public class Box<T>
{
    public Box(public T value { get; }) { }
}

public class Shelf<T>
{
}

const box = new Box(5);                                 // compiles: the argument fixes T as int
Shelf<string> shelf = new Shelf();                      // compiles: the declared type fixes T as string
const empty = new Shelf();                              // compile error: nothing fixes T; write new Shelf<string>()
```

**A generic object that plain PHP creates without type arguments** (`new PaginatedList($rows)`, or Laravel's container) has its bounds as its type arguments, checked where it enters PHP# instead of trusted. Under `PaginatedList<TItem : DatabaseEntity>`, a raw page is a `PaginatedList<DatabaseEntity>`. A parameter that needs `PaginatedList<Order>` throws `TypeError`. A method that takes any page is generic. PHP# code never creates a raw object, because a type argument it can't fix or infer is a compile error.

<!-- example raw-generic-objects: statements
use App\PaginatedList;
$rows = [new App\Order()];
$report = new App\Report();
-->
```php
$page = new PaginatedList($rows);                       // plain PHP: no type arguments, so the page is a PaginatedList<DatabaseEntity>
$report->show($page);                                   // throws TypeError: show needs a PaginatedList<Order>
$report->count($page);                                  // accepted: TItem is fixed as DatabaseEntity
```

<!-- example raw-generic-objects -->
```csharp
public class Report
{
    public void show(PaginatedList<Order> page) { … }
    public int count<TItem : DatabaseEntity>(PaginatedList<TItem> page) { … }
}
```

**Every type argument is carried at runtime, written or inferred,** so a generic method can use a type parameter that its call inferred:

<!-- example inferred-type-arguments: statements
Inbox inbox = new Inbox();
List<Order> orders = [new Order()];
Any message = new Order();
-->
```csharp
public class Inbox
{
    public bool holds<TItem>(List<TItem> items, Any value) => value is TItem;
}

inbox.holds(orders, message);   // compiles: TItem is inferred as Order, and value is TItem tests Order when the code runs
```

A collection's elements are still checked where it enters from plain PHP (section 12).

**`serialize` keeps a collection,** so a queued job or a cached value may hold one. `unserialize` gives a `Map` back as a `Map` and a `Set` as a `Set`, with the same entries in the same order, alone or inside a `List`, a struct or a class, in the same process or a fresh one, and whatever classes it allows, as it gives back an array. A collection's type arguments are the types of its elements, so the copy is a `Map<string, int>` exactly when the original is, and a field whose read-back collection holds a wrong entry throws the `Sharp.InvalidFields` its struct's or class's `parse` throws (section 10).

**Declaring type parameters:**

- **Names start with `T`:** `TItem`, `TKey`. Another capitalized name is a naming warning, as a name out of its case is (section 24).
- **The bound is written inline:** `<TItem : DatabaseEntity>`.
- **Several bounds use `&`:** `<TItem : DatabaseEntity & Shareable>`.
- **`Object` bounds a type parameter to any object,** `<TValue : Object>`. It joins other bounds with `&`, as in `<TValue : Object & Shareable>`.
- **A bound may name another type parameter,** `<TPart : TItem>`. A `TPart` then goes wherever a `TItem` goes. It is a bound alone, and a bound that leads back to its own type parameter, such as `<TItem : TItem>`, is a compile error.
- **A type parameter with no bound may hold `null`,** so it is bounded by `Any?`.
- **A generic class may be PHP's `Serializable`** through a plain PHP library's interface or parent class. The engine keeps its type arguments ahead of the text its `serialize` writes, and `unserialize` gets that text alone, so the copy is a `PaginatedList<Order>` exactly when the original is.
- **A class value keys a `Map` and joins a `Set`,** through the `==` and the hash of its whole type (section 12), so it satisfies `Equatable` too. `Class<PaginatedList<Order>>` and `Class<PaginatedList<Invoice>>` are two keys, though both run as the plain class name.

<!-- example type-parameter-bounds: members -->
```csharp
public class PaginatedList<TItem : DatabaseEntity> { … }
public PaginatedList<TItem> list<TItem : DatabaseEntity>(Query<TItem> query) { … }
public TValue keep<TValue : Object>(TValue value) => value;
public TValue shared<TValue : Object & Shareable>(TValue value) => value;                       // compiles: Object joins Shareable
public TItem either<TItem : DatabaseEntity, TPart : TItem>(TItem whole, TPart part) => part;   // compiles: a TPart goes where a TItem goes
public void joined<TItem, TPart : TItem & Shareable>() { }                                      // compile error: a type parameter is a bound alone
public void looped<TItem : TItem>() { }                                                         // compile error: the bound of TItem leads back to TItem
```

**`Equatable` bounds a type parameter to the types that have `==`** (section 19). Every such type satisfies it with no declaration: a number, a string, `bool`, an enum whose cases' fields all have `==`, a collection whose elements have `==`, a struct whose fields have `==`, and a class that declares or inherits `operator ==`. A class value does too. A function, `Object`, `Any` and a class without `operator ==` don't. A type parameter has `==` only through its bound, so `==`, `!=`, `contains`, `indexOf` and `remove` on a `List`, a `Map` key, a `Set` element and a value pattern each need `Equatable` or a class or interface bound that declares or inherits `operator ==`. `Equatable` is a bound and never a value's type, so `List<Equatable>` is a compile error. It joins other bounds with `&`, as in `<TItem : Shareable & Equatable>`, and adds no type the running code checks.

<!-- example equatable-bound: statements in Generic
public struct Point { public Point(public int x { get; }) { } }
Point point = new Point(1);
Point samePoint = new Point(1);
Cart cartA = new Cart();
Cart cartB = new Cart();
-->
```csharp
public static bool same<T : Equatable>(T a, T b) => a == b;              // compiles
public static bool sameUnbounded<T>(T a, T b) => a == b;                 // compile error: T has no ==; bound it with Equatable, as in <T : Equatable>
public static bool sameEntity<T : DatabaseEntity>(T a, T b) => a == b;   // compiles: DatabaseEntity declares operator ==
Generic.same(point, samePoint);       // compiles; runs: true, structs compare by value
Generic.same(cartA, cartB);           // compile error: Cart is outside T's bound Equatable, since Cart declares no ==
```

**A class that implements or extends a generic type takes its type argument's type:** `OrderHandler : Handler<Order>` writes `handle(Order item)`. A call from plain PHP still checks `item` against `Order` under the caller's `strict_types`, as every typed parameter is checked, and reflection reports `Order` as its type:

<!-- example generic-implementation -->
```csharp
namespace App.Handlers;

import App.Order;

public interface Handler<T>
{
    void handle(T item);
}

public class OrderHandler : Handler<Order>
{
    public void handle(Order item) { }   // compiles: takes the type argument's type
}
```

<!-- example generic-implementation: statements
$handler = new App\Handlers\OrderHandler();
-->
```php
$handler->handle("x");   // throws TypeError: handle takes an Order
```

**`Self` on a receiver typed by a type parameter is that type parameter** (section 25). In `TBox refill<TBox : Box<int>>(TBox box)`, `box.withValue(5)` returns a `TBox`, not `Box<int>`:

<!-- example self-on-type-parameters: members -->
```csharp
public class Box<T>
{
    public required Box(public T value { get; }) { }
    public Self withValue(T value) => new Self(value);
}

public TBox refill<TBox : Box<int>>(TBox box) => box.withValue(5);   // compiles: withValue returns a TBox
```

**`new TItem.fromJson(…)` calls a named constructor (section 9.1) through a type parameter,** on the type argument the code runs with. The bound marks that named constructor `required`, as it marks the main constructor for `new Self(…)` (section 25). A named constructor is not inherited, so every subclass with objects of its own declares each `required` one above it, which keeps its parameters, and leaving one out is a compile error.

- **`required` passes down.** A bound anywhere below the class that marks it `required` accepts the call, and an abstract class in between declares none of its own.
- **`new Self.fromJson(…)` calls the `required` one of the class the code was called on** (section 25).
- **A plain PHP class declares no named constructor,** so a plain PHP class as the type argument of a type parameter the code calls one through is a compile error that names the class and the constructor.
- **Calling a named constructor that is not `required` through a type parameter** is a compile error that names it:

<!-- example type-parameter-named-constructors -->
```csharp
// app/Records/Record.sharp
namespace App.Records;

public class Record
{
    public string id { get; set; } = "";
    public Record() { }
    public required Record.fromJson(Map<string, string> json) : this() { this.id = json["id"] ?? ""; }
    public Record.blank() : this() { }
    public static Self parse(Map<string, string> json) => new Self.fromJson(json);   // compiles: the fromJson of the class parse was called on
}
```

<!-- example type-parameter-named-constructors -->
```csharp
// app/Records/Document.sharp
namespace App.Records;

public abstract class Document : Record   // compiles: an abstract class declares no fromJson of its own
{
    public Document() : super() { }
}
```

<!-- example type-parameter-named-constructors -->
```csharp
// app/Records/Receipt.sharp
namespace App.Records;

public class Receipt : Document
{
    public Receipt() : super() { }
    public Receipt.fromJson(Map<string, string> json) : this() { this.id = "receipt " + (json["id"] ?? ""); }
}
```

<!-- example type-parameter-named-constructors -->
```csharp
// app/Records/Documents.sharp
namespace App.Records;

public class Documents<TDocument : Document>
{
    public TDocument load(Map<string, string> json) => new TDocument.fromJson(json);   // compiles: Record requires fromJson above Document
}
```

<!-- example type-parameter-named-constructors -->
```php
// app/Records/PlainRecord.php
namespace App\Records;

class PlainRecord extends Record
{
}
```

<!-- example type-parameter-named-constructors -->
```csharp
// app/Records/Invoice.sharp
namespace App.Records;

public class Invoice : Record
{
    public Invoice() : super() { }
    public Invoice.fromJson(Map<string, string> json) : this() { this.id = "invoice " + (json["id"] ?? ""); }   // compiles: keeps the required fromJson
}
```

<!-- example type-parameter-named-constructors -->
```csharp
// app/Records/Repository.sharp
namespace App.Records;

public class Repository<TItem : Record>
{
    public TItem load(Map<string, string> json) => new TItem.fromJson(json);   // compiles: a TItem, created by its type argument's fromJson
    public TItem empty() => new TItem.blank();                                 // compile error: Record.blank is not required, so a subclass may lack it
    public Record plain() => new Repository<PlainRecord>().load([:]);         // compile error: PlainRecord keeps no fromJson of its own
}
```
<!-- run type-parameter-named-constructors
import App.Records.Documents;
import App.Records.Invoice;
import App.Records.Receipt;
import App.Records.Record;
import App.Records.Repository;
new Repository<Invoice>().load(["id": "7"]).id;   // "invoice 7"
new Repository<Record>().load(["id": "8"]).id;    // "8"
new Documents<Receipt>().load(["id": "9"]).id;    // "receipt 9"
Invoice.parse(["id": "6"]).id;                    // "invoice 6"
-->

<!-- example required-named-constructors -->
```csharp
// app/Ledger/Entry.sharp
namespace App.Ledger;

public class Entry
{
    public Entry() { }
    public required Entry.fromJson(string json) : this() { }
}
```

<!-- example required-named-constructors -->
```csharp
// app/Ledger/Refund.sharp
namespace App.Ledger;

public class Refund : Entry   // compile error: Refund must declare fromJson, which Entry requires
{
    public Refund() : super() { }
}
```

<!-- example required-named-constructors -->
```csharp
// app/Ledger/Posting.sharp
namespace App.Ledger;

public abstract class Posting
{
    public Posting() { }
    public required Posting.fromJson(string json) : this() { }   // compiles: the fromJson each subclass declares
    public Posting.blank() : this() { }                          // compile error: an abstract class declares only required named constructors
}
```

**`TItem.make()` and `TItem.size` reach the static members of the type argument the code runs with.** The bound declares them, and a type argument that redeclares one runs its own:

<!-- example type-parameter-statics -->
```csharp
public class Maker
{
    public static virtual string make() => "maker";
    public static string size { get; } = "maker size";
}

public class Fancy : Maker
{
    public static override string make() => "fancy";
}

public class Builder<TItem : Maker>
{
    public string build() => TItem.make();           // compiles: the make of TItem's type argument
    public string size() => TItem.size;
}
```
<!-- run type-parameter-statics
new Builder<Fancy>().build();   // "fancy"
new Builder<Maker>().build();   // "maker"
new Builder<Fancy>().size();    // "maker size"
-->

**A type parameter bounded by a class takes one class.** Its code reaches the statics of its type argument and creates its objects, and a union of classes, as `Fancy|Plain`, is no one class. So `Builder<Fancy|Plain>` is a compile error at the type argument, which names the type to write, as in "`TItem`'s bound is a class, so its type argument is one class. Write `Builder<Maker>`." A type parameter bounded by an interface, or with no bound, takes a union, as `List<int|string>` does:

<!-- example class-bound-type-arguments: members
public class Maker { public static string make() => "maker"; }
public interface Labeled { }
public class Fancy : Maker, Labeled { }
public class Plain : Maker, Labeled { }
public class Builder<TItem : Maker> { public string build() => TItem.make(); }
public class Tagged<TItem : Labeled> { }
-->
```csharp
public string one() => new Builder<Fancy>().build();               // compiles
public string either() => new Builder<Fancy|Plain>().build();      // compile error: TItem's bound is a class, so its type argument is one class
public Tagged<Fancy|Plain>? labeled() => null;                     // compiles: Labeled is an interface
public List<int|string> values() => [];                            // compiles
```

### 11.1 Variance

Variance is declared on the type parameter. Classes and interfaces can both declare it.

- **`out TItem`:** the type only hands `TItem` out. A `PaginatedList<Order>` can then be used as a `PaginatedList<DatabaseEntity>`.
- **`in TItem`:** the type only takes `TItem` in. A `Validator<DatabaseEntity>` can then be used as a `Validator<Order>`.
- **No marker:** the type is invariant, and neither substitution is allowed.

The checker enforces the marker on every member. A member that breaks it is a compile error on that member's line.
A private member may break the marker, and is then reachable only through `this`.

When a missing marker blocks a substitution, the error names the marker to add:

```text
PaginatedList<Order> cannot be used as PaginatedList<DatabaseEntity>.
TItem is only returned by PaginatedList, so declare it `out TItem`.
```

There is no variance at the point of use, such as a wildcard that takes any subtype of `T`.

### 11.2 Member names

**`keyof T` is the type of the names of `T`'s readable members:** its properties, fields and methods, and the extern properties its plain PHP parent's `__get` gives (section 6.1). `T` is a class or a type parameter. Only `nameof` gives one: `nameof(Order.total)` has the type `keyof Order` (section 6.3). A `keyof Order` is a `string`, so it passes to a `string` parameter, joins a template, compares with `==` and keys a `Map`. A `keyof Order` parameter takes no other string, so a raw string is a compile error, and a member renamed later breaks the build where its name is passed:

<!-- example member-names: statements alone
namespace App.MemberNames;
import App.Order;
-->
```csharp
public class Sorter<T>
{
    public string by(keyof T column) => column;
}

Sorter<Order> sorter = new Sorter<Order>();
sorter.by(nameof(Order.total));        // compiles: nameof(Order.total) has type keyof Order
sorter.by("total");                    // compile error: a string isn't a keyof Order
string column = nameof(Order.total);   // compiles: a keyof Order is a string
```

A class inherits its parent's members, so a name of `DatabaseEntity`'s member is a name of `Order`'s: a `keyof DatabaseEntity` passes where a `keyof Order` is expected, and a `keyof Order` never passes where a `keyof DatabaseEntity` is. A `keyof T` compiles to PHP's `string`, and plain PHP receives the member's name. A member's name is one of the class's own names, so it reaches a plain PHP `literal-string` parameter, as a backed case's `value` does (section 24). A plain PHP method takes one through its `extern` line (section 23), as `Builder<TModel>.where(keyof TModel column, Any? value)` does.

## 12. Collections

PHP# has three collection types: `List<T>`, `Map<TKey, TValue>` and `Set<T>`.

- They are values, as structs (section 10) and PHP's arrays are. Assigning or passing a collection copies it only when one side later writes to it.
- A `Map`'s key is any type with `==` and `hash()` (see **Map keys** below).

<!-- example copy-on-write: statements
List<Line> a = [new Line()];
Line line = new Line();
-->
```csharp
let b = a;
b.add(line);   // b is copied here, and a is unchanged
```
<!-- run copy-on-write
a.count();   // 1
b.count();   // 2
-->

**A method that changes a collection it was given changes its own copy.** It returns the collection when the caller should see the change:

<!-- example changed-copy-returned: statements in Cart
List<Line> lines = [new Line()];
Line shipping = new Line();
-->
```csharp
public static List<Line> withShipping(List<Line> lines, Line shipping)
{
    lines.add(shipping);
    return lines;
}

lines = Cart.withShipping(lines, shipping);   // the caller keeps the change
```

**A collection held in a property changes through the property's `set`.** `this.lines.add(line)` reads `lines`, changes the copy and writes it back through `set`. Code that cannot reach the `set` cannot change the collection, and a copy in a local changes only the local:

<!-- example property-collections: statements
Order order = new Order();
Line line = new Line();
-->
```csharp
public class Order
{
    public List<Line> lines { get; private set; } = [];
    public void add(Line line) { this.lines.add(line); }   // writes lines back through its private set
}

order.lines.add(line);    // compile error outside Order: lines has a private set
let copy = order.lines;
copy.add(line);           // changes only copy
```

**Element types are checked where a collection enters PHP# from plain PHP,** not on every write. Plain PHP can change only its own copy, so a wrong element reaches PHP# code only by crossing in:

- a plain PHP caller passes a collection to a PHP# method
- a typed assignment takes a value that plain PHP returned
- plain PHP writes a PHP# property

At each crossing, the runtime checks every element once and throws a `TypeError` that names the element. An `int` where PHP# expects a `float` does not throw: it becomes a `float` (section 24). A call from PHP# to PHP# checks nothing at runtime, because the checker proved it.

**A plain PHP write into a PHP# property already holds the property's type,** by assignment, by reflection or through a reference, from a file without `strict_types` too, as every value that crosses into PHP# does. PHP's weak mode would store `5` in a `string` property as `"5"`, so a value of another type throws the `TypeError` that names the property and its type:

<!-- example plain-property-writes: statements -->
```php
$order = new App\Order();
$number = new ReflectionProperty(App\Order::class, 'number');
$number->setValue($order, 5);        // throws: "Cannot assign int to property App\\Order::$number of type string"
$order->number = 5;                  // throws: "Cannot assign int to property App\\Order::$number of type string"
$held = &$order->number;
$held = 5;                           // throws: "Cannot assign int to reference held by property App\\Order::$number of type string"
$number->setValue($order, 'A-12');
$order->number;                      // "A-12"
```

**Plain PHP's write into a PHP# collection is checked too.** A `Map` or a `Set` PHP# code makes carries its type from when it is made, as `new` of a generic class does: a literal takes the type of where it lands, `entries()`, `groupBy()`, `associateBy()` and `toSet()` the type of their result, `filter()` its receiver's, and a PHP array that crosses into a typed place the type of that place. One plain PHP makes, as `new \Sharp\Map()`, takes its type at the first typed place that holds it, a property, a parameter or a return. Each keeps its one type from then on. A property's initial value that makes one whose type names a type parameter, as `Map<string, T> index = [:]`, runs in the constructor, and a static's for each type argument, so it has the object's or the type argument's type. A literal in a generic method whose place names the method's own type parameter, as `Map<string, TItem> made = [:]` in `build<TItem>()`, has the type argument of the call. So does a literal in a lambda written in that method, since the lambda sees its method's type arguments. A parameter's default runs with neither, so a `Map` or a `Set` it makes whose type names a type parameter has no type, and plain PHP's writes into it are not checked. A key and a value plain PHP writes into a `Map`, as `$larder->stock["k"] = "x"`, and an element it adds to a `Set` cross as a PHP array's entry does, and a wrong one throws the `TypeError` at the write. A write into a `List` property, as `$order->counts[] = "z"`, a write into a `Map`'s value, as `$larder->shelves["k"][] = "x"`, an index write, a nested write, a write through a reference, `sort()`, `array_push()` and `foreach` by reference, writes plain PHP's value. The next PHP# read of the property or of the `Map`'s entries, by an index, a loop, a method such as `values()` or `filter()`, `json_encode` or `serialize`, throws the `TypeError` that names its type and the element. So PHP# code never reads an element its type doesn't hold.

**A plain PHP array keyed by `int` alone arrives as a `List`, renumbered.** PHP keeps an array's keys when a function such as `array_filter` drops elements, so plain PHP's `array<int, Person>` holds a list whose keys may have gaps. PHP# reads it as a `List<Person>` wherever it arrives, a return, a property read, an argument from a plain PHP caller and a property plain PHP writes, and its first element is at `0`. A place declared `Map<int, Person>` keeps the keys plain PHP gave. A shape whose keys are all `int`, as `list{int, int}` or `array{0: int, 1: int}`, is a `List` too, of its elements in order, and so is each array keyed by `int` alone that a shape's field, a `Map`'s value or a `List`'s element holds. An array keyed by `string` or by `int|string` stays a `Map`, and a value plain PHP types as a list or a map, as `list<int>|array<string, int>`, keeps the keys it holds, so no `string` key is ever lost. A call of a plain PHP function whose declared result is conditional, as `array_slice`'s `($preserve_keys is true ? array<int, V> : list<V>)`, gives the type of the branch its arguments pick, so `array_slice(list, 1, null, true)` arrives as a `List` whose first element is at `0`, and one whose arguments pick no branch gives both. A PHP array that crosses into a `Map` keyed by `string` gives each `int` key back as its `string`, so `"5"` stays `"5"`.

<!-- example plain-list-arrival -->
```php
class Shelf
{
    /** @return array<int, Line> */
    public static function kept(): array { return array_filter([new Line(), null]); }

    /** @return array{0: int, 1: int} */
    public static function pair(): array { return [5, 6]; }

    /** @return array{attached: array<int, int>} */
    public static function changes(): array { return ['attached' => array_filter([0, 7, 8])]; }
}
```

<!-- example plain-list-arrival -->
```csharp
// app/Stubs/Shelf.sharp
namespace App.Stubs;

import App.Shelf;

extern Shelf uses none;
```

<!-- example plain-list-arrival: statements -->
```csharp
for (const line of Shelf.kept()) { … }       // compiles: plain PHP's array<int, Line> is a List<Line>
List<Line> kept = Shelf.kept();             // compiles: kept[0] is the first kept line, whatever its key was
Map<int, Line> byKey = Shelf.kept();        // compiles: the Map keeps plain PHP's keys
List<int> pair = Shelf.pair();              // compiles: an int-keyed shape is a List
const sum = match (Shelf.pair()) { [int a, int b] => a + b, default => 0 };   // compiles: sum is 11
List<int> attached = Shelf.changes().get("attached") ?? [];   // compiles: attached[0] is 7
```

**A `Map` literal passed to a plain PHP array shape is checked against the shape, key by key.** In an application, a `Map` literal whose keys are fixed at compile time, passed where a plain PHP parameter declares a shape, as `array{code: string, raw: bool}`, gives each key the shape declares a value of the key's type. A key the shape doesn't declare, unless the shape is open, a key it requires that the literal doesn't give, and a value of another type are each a compile error that names the shape and the key:

```text
template.compose(["code": part.code, "raw": part.raw]);   compiles
template.compose(["cod": part.code, "raw": part.raw]);    error: `array{'code': string, 'raw': bool}` has no key `'cod'`
template.compose(["code": part.code]);                    error: the shape requires the key `'raw'`
template.compose(["code": part.code, "raw": part.code]);  error: the shape holds `bool` under the key `'raw'`
```

**A `List` is not a `Map`, so a `List` value where only a `Map` is declared is a compile error,** as a `List` literal is: its keys are only its positions. A plain PHP value is the exception, since it holds the keys plain PHP gave it.

<!-- example list-not-map: statements
List<int> sizes = [3, 5];
-->
```csharp
Map<int, int> bySize = sizes;                 // compile error: a List is not a Map: its keys are only its positions
```

`as` to a collection type checks every element too, wherever the value came from, and gives null if any element is wrong (section 21).

<!-- example element-checks: statements
LegacyCart legacy = new LegacyCart();
Line shipping = new Line();
-->
```csharp
List<Line> lines = legacy.lines();            // plain PHP returned it: every element is checked here
lines = Cart.withShipping(lines, shipping);   // PHP# to PHP#: nothing is checked
```

**Literals:**

<!-- example collection-literals: statements
Line lineA = new Line();
Line lineB = new Line();
Plan pro = new Plan();
Plan team = new Plan();
-->
```csharp
const lines = [lineA, lineB];                  // List<Line>
const plans = ["pro": pro, "team": team];      // Map<string, Plan>, the same name: value rule as section 16
Map<string, Plan> empty = [:];                 // an empty Map
Set<string> tags = ["vip"];                    // the declared type makes it a Set
```

PHP's `["key" => value]` is not used, because `=>` is the lambda arrow.

**An empty literal, `[]` or `[:]`, with no declared type is a compile error,** because nothing says what it holds:

<!-- example empty-literals: statements alone -->
```csharp
let messages = [];                             // compile error: an empty literal needs a type: write the type of its elements, as in List<string> messages = []
List<string> messages = [];                    // compiles
```

**A `List` literal where a `Map` is declared, or a `Map` literal where a `List` is declared, is a compile error,** at a declaration, an assignment, a default, a return or an argument, because `[:]` and `[key: value]` write a `Map`, and `[]` and `[a, b]` write a `List` or a `Set`:

<!-- example literal-kinds: statements -->
```csharp
Map<string, int> counts = [];                  // compile error: `[]` is an empty List. An empty Map is written `[:]`.
List<string> tags = [:];                       // compile error: `[:]` is an empty Map. An empty List is written `[]`.
Map<int, string> names = ["a"];                // compile error: A Map literal is written `[key: value]`.
```

**A spread copies a collection into a literal,** and the collection's type decides what it means:

<!-- example spreads: statements
List<Line> open = [new Line()];
List<Line> closed = [new Line()];
string binary = "php";
string name = "migrate";
List<string> arguments = ["--force"];
Map<string, string> config = ["root": "/srv"];
Map<string, int> defaults = ["pro": 20];
Map<string, int> overrides = ["pro": 30];
Image image = new Image();
-->
```csharp
List<Line> lines = [...open, ...closed];                  // List: closed's lines follow open's
List<string> command = [binary, "artisan", name, ...arguments];
Map<string, string> options = [...config, "root": ""];    // Map: config's entries, then "root" set to ""
Map<string, int> limits = [...defaults, ...overrides];    // Map: a key in both takes overrides' value
const mixed = [...lines, ...options];                     // compile error: a literal cannot spread a List and a Map together
image.resize(...options);                                 // compile error: only a List spreads into a call
```

- A `List` spread appends its elements in order.
- A `Map` spread copies its entries. A later key replaces an earlier one, and no key is renumbered.
- A literal cannot spread a `List` and a `Map` together.
- Only a `List` spreads into a call (section 7).

**Indexing:**

- A `List` read, `x[i]`, throws `OutOfRangeException` when the index is missing, whatever follows it. `x.getOrNull(i)` is the safe read, so `x[i] ?? fallback` is a compile error that names `getOrNull`.
- A `Map` read, `x[key]`, gives `TValue?`, null when the key is missing.
- `x[i] = v` on a `List` replaces the element at `i`, and throws `OutOfRangeException` past the end. Appending is `add`.
- `x[key] = v` on a `Map` inserts or replaces the key.
- A `List` index is an `int`. Any wider index, such as `int|string` or `Any`, is a compile error. A negative index is never in a `List`, so `items[-1]` throws `OutOfRangeException`, and the last item is `items.last()`.

<!-- example index-rules: statements
List<Line> lines = [];
Line line = new Line();
Map<string, Plan> plans = [:];
Plan pro = new Plan();
List<int> items = [1, 2, 3];
int|string id = 1;
-->
```csharp
const first = lines[0];       // throws OutOfRangeException when lines is empty
lines[0] = line;              // replaces the element at 0; throws OutOfRangeException when lines is empty
plans["pro"] = pro;           // inserts or replaces
items[-1];                    // throws OutOfRangeException: write items.last()
items.last();                 // the last item
items.getOrNull(5);           // null: index 5 is missing
items[5] ?? 0;                // compile error: a List read throws when the index is missing; write items.getOrNull(5) ?? 0
items[id];                    // compile error when id is int|string: a List index is an int
```

**A `List` index is a place,** so a changing method called through it changes the element the list stores: `grid[0].add(5)` adds to the list at index 0. A struct's fields never change (section 10), so `points[0].x = 5` stays a compile error. **A change through a `Map` index is a compile error,** because a `Map` read is a copy:

<!-- example index-changes: statements
List<List<int>> grid = [[1, 2], [3]];
Map<string, List<int>> stock = ["sku-1": [1]];
-->
```csharp
grid[0].add(5);                               // changes the list stored at index 0
grid[0].count();                              // 3
stock["sku-1"]?.add(5);                       // compile error: a Map read is a copy: read it, change it, and store it back
List<int> counts = stock["sku-1"] ?? [];
counts.add(5);
stock["sku-1"] = counts;                      // stores the changed list back
```

**A `Map` read gives `TValue?`, so it is handled where it is read,** with `??`, `?.`, `is`, `as`, `match` or `get`. A `Map` read used as a `TValue` is a compile error that names `??` and `get`:

<!-- example map-reads: statements alone
string plan = "pro";
private void charge(int price) { }
-->
```csharp
Map<string, int> prices = ["basic": 900, "pro": 2900];
int price = prices["pro"];                                  // compile error: handle a missing "pro" with ??, or read it with get
int price = prices["pro"] ?? 0;                             // compiles: 0 when "pro" is missing
int price = prices[plan] ?? throw new UnknownPlan(plan);    // compiles: throws when plan is missing
if (prices[plan] is int price) { this.charge(price); }      // compiles: runs only when plan is present
int? maybe = prices.get(plan);                              // compiles: null when plan is missing
```

Data with fixed keys is a class, so a `Map` holds keys that come from outside, where a missing key is normal.

**A `Map` with nullable values reads as any `Map` does:** a read from `Map<string, int?>` gives `int?`, so a missing key and a stored null read the same. `map.has(key)` tells them apart.

<!-- example nullable-map-values: statements -->
```csharp
Map<string, int?> limits = ["pro": null];
limits["pro"] ?? 0;          // 0: the stored null
limits["team"] ?? 0;         // 0: the missing key
limits.has("pro");           // true
limits.has("team");          // false
```

**A key read back out of a `Map<string, TValue>` is a `string`,** an all-digit key such as `"5"` included. This covers the key of `for (const [key, value] of map)` and of `keys()`.

<!-- example map-string-keys: statements
Map<string, int> stock = ["5": 1, "sku-2": 2];
-->
```csharp
void reserve(string sku, int count) { … }

for (const [sku, count] of stock) {   // stock is Map<string, int>, so sku is a string, even for the key "5"
    this.reserve(sku, count);
}
```

**Map keys:** anything with `==` and `hash()` (section 19) keys a `Map` and joins a `Set`. Two keys are one key when they are `==`, but for two numbers: every NaN is one key, though `NaN == NaN` stays false, and an `int` and a `float` are one key only when the `float` is exactly that whole number, though `==` converts the `int` to a `float` first. So `-0.0` and `0.0` are one key, and `2 ** 53 + 1` and `2.0 ** 53` are two. In a `Map<float, V>` an `int` key becomes a `float` where it enters, so the two differ only for a key type that holds both. A class value is one key per whole type, so `typeof(Box<Order>)` and `typeof(Box<Invoice>)` are two keys.

- A loop over the `Map` gives each key as the key type, as in `for (const [status, n] of counts)`, where `status` arrives as a `Status`. Writing the type, as in `const [Status status, int n]`, stays allowed. An inferred `Map`, such as a `groupBy` result, gives its keys back the same way.
- Plain PHP receives a `Map` or a `Set` as a PHP array wherever it gets one: a result, a value a generator yields, an argument, a property it reads, a value PHP# writes into a property plain PHP declares or an element of one, a constant and an attribute's argument, and so does each `Map` and `Set` a `List` or a `Map` holds, at every depth. A value crosses by what it is when the code runs, so that holds where the declared type is `Any`, `Any?`, a type parameter, an `Iterable` or a union that holds a `Map` too, at an `[Import]` call's argument as at plain PHP's. A `Map` is the array of its entries and a `Set` the list of its elements. A property it reads through `get_object_vars`, `get_mangled_object_vars`, `get_class_vars`, an `(array)` cast, `foreach`, `array_walk` or an `ArrayIterator` over the object, `ReflectionProperty::getValue` and `getRawValue`, after an `isInitialized()` or a `setValue()` too, and `ReflectionClass`'s static property reads is the array too, `empty()` tests that array, `var_export` prints it, and reflection gives each such property, parameter and result the type `array`. A reference plain PHP takes to an instance property whose type names each `Map` and `Set` it can hold, as `Map<string, Set<int>>` does and `Map<string, Any>` does not, is a reference to that array, as `ksort`, `$r = &$order->tags` and `foreach` by reference take one, and each array assigned through it must fit the property's type. PHP# code reading its own property still gets the `Map`, and a PHP array plain PHP writes there, directly or through a reference, becomes one again. A lazy `Iterable` reaches plain PHP as a `Traversable`, unread, and each element crosses as plain PHP reads it.
- A PHP array's keys are `int` or `string`, so a `Map` keyed by a backed enum gives each case's backing value as its key, as an array crossing the other way gives each backing value as its case. A `Map` keyed by anything else, a class, a struct, a collection or an enum without backing values, reaches plain PHP as the `Map` itself, the one collection plain PHP receives as the object. Passing it to a plain PHP parameter that names `array` is a compile error that names the parameter and the key type. `counts.count()` counts any `Map`, whatever its keys.
- Every other `Map` crosses as a PHP array, as one keyed by `int|string` or by a backed enum beside its backing type does. When two of its keys would become one PHP array key, as `5` and `"5"` do, or a backed enum case and its backing value, the crossing throws a `ValueError` that names both keys: "This `Map` holds both `5` and `"5"`, which plain PHP keeps as one key, so it can't become a plain PHP array". A case is named as `Shop.Level.Low`. It never keeps one entry and drops the other. Only the keys a `Map` holds show this, so the check runs where the `Map` crosses, and a `Map` with no two such keys costs nothing extra.
- A plain PHP parameter that takes only objects, such as `\Countable`, `\Traversable`, `object` or an interface, takes the `Map` or the `Set` itself. One that names `array`, `iterable` or `mixed`, or declares no type, takes the PHP array. One typed by a `@template` takes what the template's bound takes, so `T of \Countable` takes the `Map` itself, and a `T` without a bound takes the PHP array. Passing a value that may be one of several collections, as a `Map<string, int>|Set<string>`, to a reference parameter that takes the PHP array is a compile error that names the parameter, since the array plain PHP writes back doesn't say which collection it is. A local of one collection, typed or declared with `let`, which holds the type of its first value for its whole life, goes there as its PHP array and comes back as that collection of what plain PHP wrote, so PHP's `ksort` gives a `Map<Status, int>` back keyed by its cases. A property plain PHP declares, on the class or a parent, and an element of one, takes what a parameter of its type takes, whether PHP# writes it with `=`, `??=` or another compound assignment, in an object initializer or a `with`, or through a collection method such as `add`, and a name a plain PHP class with `__set` declares no property for takes what `__set`'s value parameter takes. A function value gives a `Map` or a `Set` as a PHP array when the closure behind it is plain PHP, and as itself when it is PHP#.

<!-- example enum-map-keys -->
```csharp
// app/Status.sharp
namespace App;

public enum Status : string
{
    case Open = "open";
    case Paid = "paid";
    case Refunded = "refunded";

    public string label() => match (this) {
        Status.Open => "Awaiting payment",
        Status.Paid => "Paid",
        Status.Refunded => "Refunded",
    };
}
```

<!-- example enum-map-keys -->
```csharp
// app/OrderStats.sharp
namespace App;

public class OrderStats
{
    public Map<Status, int> countByStatus(List<Order> orders)
    {
        Map<Status, int> counts = [:];
        for (const order of orders) {
            counts[order.status] = (counts[order.status] ?? 0) + 1;
        }
        return counts;
    }

    public List<string> report(List<Order> orders)
    {
        List<string> lines = [];
        for (const [Status status, int n] of this.countByStatus(orders)) {   // status arrives as a Status
            lines.add(`${status.label()}: ${n}`);
        }
        return lines;
    }
}
```

<!-- example enum-map-keys: statements
OrderStats stats = new OrderStats();
List<Order> orders = [new Order()];
-->
```csharp
for (const [status, n] of stats.countByStatus(orders)) { … }   // compiles: status arrives as a Status, with no type written
for (const [status, group] of orders.groupBy(o => o.status)) { … }   // compiles: an inferred Map's keys arrive as Status too
Map<Username, Order> byUser = [:];                               // compiles: a struct whose every field has == keys a Map by its whole value
```

**Only a literal takes its collection type from where it lands.** A list literal passed where a `Set` or a tuple is expected becomes one. A literal that `??`, `? :` or a `match` arm gives lands where their value lands, so `Set<string> tags = given ?? ["vip"];` makes it a `Set`, as `Set<string> tags = ["vip"];` does. A `List` variable passed there is a compile error, and where a `Set` is expected the error names `toSet()`:

<!-- example set-arguments: statements in Tags -->
```csharp
public static void apply(Set<string> tags) { … }
Tags.apply(["vip", "new"]);                      // compiles: the literal becomes a Set
```

<!-- example set-variable-arguments: statements in Tags
List<string> picked = ["vip"];
-->
```csharp
public static void apply(Set<string> tags) { … }
Tags.apply(picked);                              // compile error: a List is not a Set; write picked.toSet()
```

**Methods** of a collection:

<!-- example collection-read-methods: statements
List<Line> lines = [new Line()];
Line line = new Line();
-->
```csharp
lines.filter(l => !l.refunded).sumOf(l => l.amount);
lines.map(l => l.name);
lines.first(l => l.free);
lines.any(l => l.free);
lines.contains(line);
lines.groupBy(l => l.product.id);
lines.associateBy(l => l.id);
lines.sortedBy(l => l.amount);
lines.sortedBy(l => [l.product.id, l.amount]);
lines.sortedByDescending(l => l.amount);
lines.sortedWith((a, b) => a.amount <=> b.amount);
lines.minBy(l => l.amount);
lines.firstOrNull(l => l.free);
lines.indexOfFirst(l => l.free);
lines.partition(l => l.free);
lines.all(l => l.amount > 0);
lines.fold(0, (total, l) => total + l.amount);
lines.reversed();
lines.distinct();
lines.slice(1, 3);
lines.chunked(2);
lines.windowed(2);
```

- Methods that read return a new collection.
- `first` throws `OutOfRangeException` when nothing matches, as a bare index read does, and so does a `slice` range outside the list. `firstOrNull` gives null instead. `minBy` and `maxBy` throw `OutOfRangeException` on an empty list, as `max()` does. A `chunked` or `windowed` size below 1 throws `ValueError`.
- A `sortedBy` key of type `T` or `T?` compiles exactly when `<` compiles on two non-null `T` values. The nullable part is allowed, and it sorts first. So does a key of `minBy`, `maxBy` and `sortedByDescending`. Every sort is stable, so equal keys keep their order.
- **A tuple key orders element by element:** `[l.product.id, l.amount]` sorts by the product, then by the amount. It compiles exactly when each element's type is a key, and a null element sorts first. A `List` that is no tuple has no order. `sortedWith` takes a comparison of two elements, below 0 when the first goes first, for any other order.
- A method takes on the effects of each function it is given (section 29), so `lines.map(l => l.name)` is pure.
- Methods that change a collection change it in place:

<!-- example collection-write-methods: statements
List<Line> lines = [new Line()];
Line line = new Line();
Map<string, Plan> plans = [:];
Plan pro = new Plan();
-->
```csharp
lines.add(line);
lines.insert(0, line);
lines.remove(line);           // by value: the first equal element, and true when one was removed
lines.clear();
plans["pro"] = pro;
plans.remove("pro");          // by key
```

A `List`'s `removeAt(i)` removes the element at index `i` and returns it, `removeFirst()` and `removeLast()` remove the first and the last element and return it, and `set(i, v)`, which `list[i] = v` runs, replaces the element at `i` and returns the one it replaced. Each throws `OutOfRangeException` on an empty list or an index outside the list. A `Map`'s `set(key, value)`, which `map[key] = value` runs, inserts the key with the value, or replaces the value of an equal key. `isEmpty()` and `isNotEmpty()` tell whether a `List`, a `Map` or a `Set` holds no element.

**Each collection does the natural thing with a method:**

- `remove` removes a value from a `List` and a key from a `Map`.
- On a `List`, `remove(value)` removes the first element equal to `value` by `==` (section 19), renumbers the list, and returns `bool`: true when it removed one.
- `filter` renumbers what a `List` keeps, and keeps a `Map`'s keys.

<!-- example natural-remove: statements
List<Line> lines = [new Line()];
Map<string, Plan> plans = ["pro": new Plan()];
-->
```csharp
List<string> tags = ["a", "b", "a"];
tags.remove("a");                     // true, and tags is ["b", "a"]
tags.remove("z");                     // false, and tags is unchanged
lines.filter(l => l.free);            // List<Line>, renumbered
plans.filter(p => p.active);          // Map<string, Plan>, keys kept
```

`contains`, a `List`'s `remove`, `indexOf` and `Set<T>` need `==` on the element type (section 19). Without it, they are a compile error.

**`flatMap` and `toList()`:** a `List`'s `flatMap` gives, in order, the elements of each `Iterable` its function gives. `toList()` gives the elements of any `Iterable` as a `List` of their own. A `Map` is an `Iterable` of its `[key, value]` entries, so both give a `Map`'s entries:

<!-- example flat-map-to-list: statements
List<List<int>> rows = [[1, 2], [], [3]];
List<List<int>> none = [];
Set<string> tags = ["vip", "new"];
Map<string, int> stock = ["tea": 3, "pie": 7];
List<Map<string, int>> shelves = [stock, ["jam": 1]];
-->
```csharp
rows.flatMap(row => row);              // [1, 2, 3]
none.flatMap(row => row);              // []
tags.toList();                         // ["vip", "new"]
stock.toList();                        // [["tea", 3], ["pie", 7]]
stock.values().toList();               // [3, 7]
shelves.flatMap(shelf => shelf);       // [["tea", 3], ["pie", 7], ["jam", 1]]
rows.flatMap(row => row.count());      // compile error: an int is no Iterable
List<int> counts = shelves.flatMap(shelf => shelf);   // compile error: the entries are [string, int]
```

**`join` puts a separator between the strings of a `List<string>`.** Only a list of strings has it, so a list of anything else maps its elements to text first:

<!-- example list-join: statements
List<string> tags = ["vip", "new"];
List<int> ids = [3, 5];
-->
```csharp
tags.join(", ");                            // "vip, new"
ids.map(id => `#${id}`).join(" ");          // "#3 #5"
ids.join(", ");                             // compile error: only a List<string> has join
```

**`toCsv()` writes a `List<string>` as one CSV record of RFC 4180,** the write side of a `string`'s `parseCsv()`, which reads it back. A field that holds a comma, a quote, a carriage return or a line feed is quoted, with each quote doubled, and every other field is written as it is:

<!-- example list-csv: statements -->
```csharp
const record = ["A-1", "Mug, large", "12.50"].toCsv();
record.parseCsv();                          // ["A-1", "Mug, large", "12.50"]
record.length();                            // 22: A-1,"Mug, large",12.50
```

**`SortedMap<TKey, TValue>` and `SortedSet<T>`, in `Sharp.Collections`, keep their keys in order.** Each has every member a `Map` or a `Set` has, and a loop reads it in key order. `first()` and `last()` give the value of the smallest and the largest key, or the smallest and the largest element, and throw `OutOfRangeException` on an empty one. `floor(key)` and `ceiling(key)` give the nearest key at or below `key` and at or above it, or null. `range(from, to)` gives the entries from `from` up to `to`, with `to` left out. They order by the key's `operator <=>`, as `<` orders two keys (section 19), or by a `Function<int(T, T)>` the constructor takes, which gives below 0 when its first key goes first. Two keys that order at 0 are one key. Without a comparison, a key whose class declares no `<=>` throws `ValueError` at its first comparison.

**`PriorityQueue<TElement, TPriority>`, in `Sharp.Collections`, gives the element of the lowest priority first:** `enqueue(element, priority)`, `dequeue()`, `peek()`, `count()` and `isEmpty()`. It orders its priorities as a `SortedMap` orders its keys, and gives elements of one priority in no set order. `dequeue()` and `peek()` throw `UnderflowException` on an empty queue:

<!-- example sorted-collections: statements
import Sharp.Collections.PriorityQueue;
import Sharp.Collections.SortedMap;
import Sharp.Collections.SortedSet;
-->
```csharp
const prices = new SortedMap<string, int>();
prices["pear"] = 4;
prices["apple"] = 5;
prices["fig"] = 2;
prices.keys();                              // ["apple", "fig", "pear"]
prices.first();                             // 5
prices.floor("banana");                     // "apple"
prices.ceiling("banana");                   // "fig"
prices.range("b", "p").keys();              // ["fig"]
const sizes = new SortedSet<int>((int a, int b) => b - a);
sizes.add(3);
sizes.add(9);
sizes.first();                              // 9: the comparison puts the largest first
const tasks = new PriorityQueue<string, int>();
tasks.enqueue("ship", 9);
tasks.enqueue("plan", 1);
tasks.dequeue();                            // "plan"
tasks.count();                              // 1
```

The complete method list is specified with the standard library.

**`Iterable<T>` is anything a loop can read,** and it compiles to PHP's `iterable`. `List<T>` and `Set<T>` are `Iterable<T>`. A `Map<TKey, TValue>` is an `Iterable<[TKey, TValue]>` of its entries, and `values()` gives its values (section 17):

<!-- example iterables: statements
OrderReport report = new OrderReport();
Cart cart = new Cart();
-->
```csharp
public class OrderReport
{
    public int tally(Iterable<Order> orders)
    {
        let n = 0;
        for (const order of orders) { n += 1; }
        return n;
    }

    public List<string> paidNumbers()
    {
        Iterable<Order> orders = Order.query().cursor();   // Laravel's LazyCollection, read as an Iterable<Order>
        return orders
            .filter(o => o.paid)
            .map(o => o.number)
            .take(100)
            .toList();                                      // runs here, and reads rows only until it has 100
    }
}

report.tally(cart.orders);                  // compiles: a List<Order> is an Iterable<Order>
report.tally(Order.query().cursor());       // compiles: rows are read one at a time
Iterable<Order> rows = Order.query().cursor();
rows[0];                                    // compile error: an Iterable has no index
```

- **Lazy operations,** such as `filter`, `map` and `take`, return an `Iterable<T>`. They run only when `toList()` or a loop reads the result.
- **A lazy plain PHP source,** such as a generator or Laravel's `LazyCollection`, is checked element by element as it is read, so each element is checked where it enters PHP# (section 11).
- **The lazy operations apply to every `Iterable<T>` implementation:** a PHP# class that names `Iterable<T>` and a plain PHP `Traversable`, such as Laravel's `LazyCollection`. The class's own member of the same name wins, so `Order.query().cursor().filter(…)` runs `LazyCollection`'s own `filter`, and `toList()`, which it doesn't declare, is the lazy operation. Held as an `Iterable<T>`, as `orders` is above, the value has only the lazy operations.

**A class is an `Iterable<T>` when its header names `Iterable<T>` and it declares `Iterable<T> elements()`.** `elements()` returns an `Iterable` or uses `yield`. A loop, `toList()` and the lazy operations read `elements()`, and the object is an `Iterable<T>` everywhere one is taken, carrying its element type as every PHP# generic does. A class that names `Iterable<T>` and declares no `elements()` is a compile error that names the member:

<!-- example iterable-classes -->
```csharp
// app/Bag.sharp
namespace App;

public class Bag<TItem> : Iterable<TItem>
{
    private List<TItem> items = [];

    public void add(TItem item) { this.items.add(item); }

    public Iterable<TItem> elements() => this.items;       // the one member Iterable<T> asks for; a body with yield works too
}
```

<!-- example iterable-classes -->
```csharp
// app/Tally.sharp
namespace App;

public class Tally : Iterable<[string, int]>
{
    private Map<string, int> counts = ["tea": 3, "pie": 7];

    public Iterable<[string, int]> elements() => this.counts;   // a Map is an Iterable of its entries
}
```

<!-- example iterable-classes -->
```csharp
// app/Crate.sharp
namespace App;

public class Crate : Iterable<int> { }                     // compile error: Crate is an Iterable<int> and declares or inherits no elements()
```

A struct names `Iterable<T>` as a class does, and an interface that names it asks each class that implements it for `elements()`. Plain PHP reads the object through the `getIterator()` PHP# writes for it, so the type declares no `getIterator()` of its own.

<!-- example iterable-classes -->
```csharp
// app/Feed.sharp
namespace App;

public interface Feed<TItem> : Iterable<TItem> { }         // compiles: each class that implements Feed declares elements()
```

<!-- example iterable-classes -->
```csharp
// app/Countdown.sharp
namespace App;

public struct Countdown : Iterable<int>
{
    public Countdown(public int from { get; }) { }

    public Iterable<int> elements() => [this.from, this.from - 1];   // compiles: a struct is an Iterable<int> too
}
```

<!-- example iterable-classes: statements
public class Tallies { public int tally(Iterable<Order> orders) { let n = 0; for (const order of orders) { n += order.total; } return n; } }
Tallies report = new Tallies();
Order paid = new Order();
-->
```csharp
const bag = new Bag<Order>();
bag.add(paid);
let count = 0;
for (const order of bag) { count += 1; }                   // compiles: a Bag<Order> is an Iterable<Order>
bag.filter(o => o.paid).toList().count();                  // compiles: Iterable<T>'s lazy operations
report.tally(bag);                                         // compiles: tally takes an Iterable<Order>
for (const [name, n] of new Tally()) { count += n; }       // compiles: each element is a [string, int]
bag is Iterable<Order>;                                    // true, read from Bag's type argument with no element read
```

**Plain PHP receives the object as a `Traversable` whose `foreach` reads `elements()`.** An element that is a `[key, value]` tuple comes as that key and value, as a `Map` reaches plain PHP as the array of its entries, and any other element comes under the keys 0, 1, 2 and on. So plain PHP's `foreach ($tally as $pair)` gets only the value of each pair, as it does for a `Map`:

<!-- example iterable-classes: statements -->
```php
$bag = new App\Bag();
$bag->add(new App\Order());
$bag instanceof Traversable;                // true
foreach ($bag as $i => $order) { }
$i;                                         // 0
iterator_to_array(new App\Tally());         // ["tea" => 3, "pie" => 7]
```

**`yield` produces an `Iterable<T>` lazily.** A method that returns `Iterable<T>` produces its next value with `yield value;`, and every element of another `Iterable<T>` with `yield ...other;`:

<!-- example yield: statements
OrderImport importer = new OrderImport([], new Order());
-->
```csharp
import Illuminate.Support.Facades.File;

public class OrderImport
{
    public OrderImport(private List<Order> pending, private Order latest) { }

    public Iterable<List<string>> rows(string path)
    {
        for (const string line of File.lines(path)) {
            yield line.parseCsv();                   // compiles: each row is a List<string>
        }
    }

    public Iterable<Order> all()
    {
        yield ...this.pending;                       // every pending order, in order
        yield this.latest;                           // then the latest one
    }

    public List<List<string>> preview() => this.rows("orders.csv").take(100).toList();   // reads only the first 100 lines
}

importer.rows("missing.csv");                                    // runs nothing yet: the missing file throws when a loop reads the rows
public Iterable<Order> numbers() { yield this.latest.number; }   // compile error: a string is not an Order
public List<Order> recent() { yield this.latest; }               // compile error: yield needs a method that returns Iterable<T>
```

- **Each yielded value is checked against `T`.**
- **`yield ...other;` replaces PHP's `yield from`,** with PHP#'s spread. `other` is an `Iterable<T>`.
- **The body runs only when a loop or `toList()` reads the result,** so an error inside it surfaces at the loop, not at the call.
- **A method with `yield` compiles to a PHP generator.** PHP's `send()` and two-way generators are not part of PHP#, so `yield` is a statement and gives no value back. Async is a separate future design.

## 13. `readonly`

`readonly` has one rule throughout the language:

- **Before a type,** it makes that value readonly.
- **After a method's parameters,** it makes `this` readonly inside that method.

<!-- example readonly-positions: members
private Customer owner = new Customer();
-->
```csharp
public void render(readonly Order order)          // the parameter
public readonly Customer customer => this.owner;  // the returned value
public Money totalIn(string currency) readonly    // this, inside the method
public readonly Customer buyer() readonly         // both
```

**Through a `readonly` value, only reading is allowed:**

- property `get` accessors
- methods marked `readonly`

Setters and unmarked methods are compile errors. **`readonly` holds only types PHP# checks,** so a plain PHP class under `readonly` is a compile error:

<!-- example readonly-values: members -->
```csharp
import Illuminate.Database.Eloquent.Model;

public class Order
{
    public string number { get; set; } = "";
    public Money totalIn(string currency) readonly { … }
    public void markPaid() { … }
}

public void render(readonly Order order)
{
    order.number;              // compiles: a get accessor
    order.totalIn("USD");      // compiles: totalIn is marked readonly
    order.markPaid();          // compile error: markPaid is not marked readonly
    order.number = "A-1";      // compile error: order is readonly
}

public void show(readonly Model model) { … }   // compile error: Model is a plain PHP class, which PHP# does not check
```

**Readonly reaches everything read through it.** A property read through a `readonly` value is also `readonly`, so `order.lines` is readonly when `order` is.

- `order.lines.add(line)` writes `lines` back through its `set` (section 12), so through a `readonly` `order` it is a compile error.
- A copy in a local belongs to the local. After `let copy = order.lines`, `copy.add(line)` is allowed and leaves `order` unchanged.
- The objects inside that copy stay readonly.

**A readonly value never goes where a writable one is expected.** Passing, returning or storing it is a compile error that names the `readonly` to declare:

<!-- example readonly-stays-readonly: members -->
```csharp
public Order give(readonly Order order) => order;   // compile error: declare the return type `readonly Order`
```

**A `readonly` method is checked as if `this` were a `readonly` value.** Inside it, `this` cannot be written, and only other `readonly` methods can be called on it. An override or an implementation of a `readonly` method is `readonly` too, so a call through a readonly value never runs a body that changes `this`:

<!-- example readonly-overrides -->
```csharp
// app/Shape.sharp
namespace App;

public class Shape
{
    public virtual int area() readonly => 0;
}
```

<!-- example readonly-overrides -->
```csharp
// app/Square.sharp
namespace App;

public class Square : Shape
{
    public override int area() => 1;   // compile error: Square.area overrides the readonly Shape.area, so declare it `area() readonly`
}
```

**A `readonly` that can't matter warns,** as a `?` that can't matter does, and the code compiles: before a type whose value never changes, an `int`, a `string`, an enum, a struct, a function type or a collection of them, in `is` or `as`, and after the parameters of a method with no `this` to keep:

<!-- example readonly-cannot-matter: members -->
```csharp
public int count(readonly List<int> numbers) => 0;   // compiles, and warns: a List<int> never changes, so write List<int>
```

**A method's `readonly` is its last clause,** after `on` and `uses`, because it talks about the `this` inside the body. Anywhere else it is a compile error that says to write it last:

<!-- example readonly-last-clause -->
```csharp
// app/Quotes.sharp
namespace App;

public interface Quotes
{
    Money quote(Cart cart) uses Http readonly;   // compiles: readonly is the last clause
    Money price(Cart cart) readonly uses Http;   // compile error: write readonly last
}
```

**Operators, `int hash()` and `string toString()` are readonly by definition.** The language runs them on readonly values with no marker, so a body that changes an operand or `this` is a compile error, and an operator that returns an operand declares its return type `readonly`:

<!-- example readonly-by-definition -->
```csharp
// app/Points.sharp
namespace App;

public class Points
{
    public int count { get; set; } = 0;

    public static Points operator +(Points a, Points b)
    {
        a.count += b.count;                       // compile error: an operator's operands are readonly
        return new Points();
    }

    public static readonly Points operator -(Points a, Points b) => a;   // compiles: it returns a readonly operand

    public int hash() => this.count++;            // compile error: hash() is readonly
    public string toString() => "points";         // compiles
}
```

**A missing marker is a compile error that names the fix:**

```text
render cannot call order.totalIn: order is readonly.
totalIn does not change Order, so declare it `totalIn(string currency) readonly`.
```

**Fields, properties and classes use `{ get; }`, not `readonly`.** A get-only property already cannot be reassigned. `readonly` on a field, a promoted constructor parameter or a class is a compile error that names `{ get; }`:

<!-- example readonly-fields -->
```csharp
// app/Invoice.sharp
namespace App;

public class Invoice
{
    private readonly Money total;                       // compile error: declare a get-only property with { get; }
}
```

<!-- example readonly-fields -->
```csharp
// app/Bill.sharp
namespace App;

public class Bill
{
    public Bill(public readonly Money due) { }          // compile error: declare a get-only property with { get; }
}
```

<!-- example readonly-fields -->
```csharp
// app/Quote.sharp
namespace App;

public class Quote
{
    public Quote(public Money due { get; }) { }         // compiles
}
```

<!-- example readonly-fields -->
```csharp
// app/Receipt.sharp
namespace App;

public readonly class Receipt { … }                     // compile error: declare its properties with { get; }
```

`readonly struct` is a compile error too, because every struct is already read-only (section 10).

## 14. Functions as values

### 14.1 Function types

A function type is written as `Function<ReturnType(ParameterTypes)>`, in the same order as a method declaration. One name covers every case, including functions that return `void`. Its effects go inside the brackets, after the parameter types, as in `Function<Charge(Cart) uses Http>` (section 29).

<!-- example function-types: members -->
```csharp
Function<Money?(readonly Line, string)> priceOf = (line, currency) => null
Function<void(Order)>? onPaid = null
public Function<bool(Order)> eligibleFor(Account account)
```

**A function fits a function type when it takes every parameter the type passes.** A function for a type that returns `void` gives no value, and the result of a call that returns `void` is no value to use:

<!-- example function-fit: statements
List<int> seen = [];
-->
```csharp
Function<int(int, int)> pair = (int a) => a;        // compile error: Function<int(int, int)> takes 2 parameters, and this lambda takes 1
Function<void(int)> sink = (int n) => n * 2;        // compile error: this lambda gives a value
Function<void(int)> record = (int n) => seen.add(n);
const kept = seen.clear();                          // compile error: seen.clear() returns nothing
```

**Any expression that gives a function is called the same way,** with the arguments in parentheses after it: a list element, a call's result, a conditional or a lambda.

<!-- example function-calls: statements
List<Function<int(int)>> steps = [(int n) => n + 1, (int n) => n * 2];
bool doubles = true;
-->
```csharp
const adder = (int a) => (int b) => a + b;
int first = steps[0](5);                             // 6
int picked = (doubles ? steps[1] : steps[0])(5);     // 10
int sum = adder(2)(3);                               // 5
```
<!-- run function-calls
first;    // 6
picked;   // 10
sum;      // 5
-->

### 14.2 Lambdas

A lambda is written as a bare arrow. Parameter types are optional when the checker can infer them.

<!-- example lambdas: statements
List<Line> lines = [new Line()];
int minimum = 500;
-->
```csharp
lines.filter(l => !l.refunded);
const check = (Order order) => order.total >= minimum;
const price = (readonly Line line, string currency) => {
    if (line.free) { return Money.zero(currency); }
    return line.product.priceIn(currency);
};
```

A lambda captures the variables it uses from around it, with no `use` list. It captures the variable itself, not a copy:

<!-- example capture-by-reference: statements -->
```csharp
let count = 0;
const increment = () => { count += 1; };
increment();   // count is now 1
```
<!-- run capture-by-reference
count;   // 1
-->

A lambda that changes a captured collection, with `add`, `insert`, `remove`, `clear` or an index write, changes the variable itself:

<!-- example capture-and-change: statements
List<int> numbers = [1, 2, 3];
-->
```csharp
List<int> seen = [];
numbers.filter(n => { seen.add(n); return true; });   // seen now holds every number
```
<!-- run capture-and-change
seen.count();   // 3
-->

### 14.3 Methods as values

A method named without parentheses is a function value. It is found when the code runs, so it works when the class lives in another file. PHP writes this as `Str::slug(...)`.

<!-- example method-values: statements
import Illuminate.Support.Str;
List<string> names = ["Spring Sale"];
-->
```csharp
names.map(Str.slug);
```

A method of a `List`, `Map` or `Set`, an extension method (section 26) and a plain PHP function are values the same way, as in `allowed.contains`, `name.trim` and `Pdf.render` (section 23). A collection is a value (section 12), so a collection's method value holds the collection as it was when the value was made:

<!-- example method-value-kinds: statements
List<int> allowed = [1, 3];
-->
```csharp
const has = allowed.contains;
allowed.add(5);
List<int> kept = [1, 2, 5].filter(allowed.contains);   // [1, 5]
```
<!-- run method-value-kinds
has(5);        // false
kept;          // [1, 5]
-->

**A method that changes a collection is no function value**, since it would change only that copy. A lambda that calls it changes the collection itself:

<!-- example changing-method-values: statements
List<int> seen = [];
-->
```csharp
const add = seen.add;                       // compile error: write a lambda that calls it, as in value => seen.add(value)
const record = (int value) => seen.add(value);
```

**A plain PHP function that changes a variable it is given is no function value,** since PHP# passes every argument by value. A call that would pass it a PHP# variable to change is an error too:

<!-- example by-reference-functions -->
```php
namespace Vendor\Lists;

function reorder(array &$items): bool { return sort($items); }
```

<!-- example by-reference-functions: statements
import Vendor.Lists;
List<int> numbers = [3, 1, 2];
-->
```csharp
const reorder = Lists.reorder;              // compile error: Lists.reorder changes the variable it is given
Lists.reorder(numbers);                     // compile error: Lists.reorder changes the variable it is given, so numbers can't go there
List<int> sorted = numbers.sortedBy(n => n);
```
<!-- run by-reference-functions
sorted;   // [1, 2, 3]
-->

A plain PHP class may have both a property and a method of one name. Reading the name gives the property, as in PHP, except where the code takes the read as a function and the property's type doesn't fit that function type. There the read gives the method's value, as in `names.map(gadget.label)`, where `label` is both a `string` property and a method.

**A plain PHP class that defines `__get` has the properties it reads,** as an Eloquent model has the relations it loaded. A property's type is the one an `@property` or `@property-read` line on the class or its parents gives it, and `Any?` without one. `subscription.contact` reads the property, as `$subscription->contact` does in PHP, even when the class has a method `contact`. By the rule above, the read gives the method's value only where the code takes it as a function. `subscription.contact()` calls the method.

<!-- example magic-properties -->
```php
/** @property-read Contact $contact */
class Subscription
{
    public function contact(): Contact { return new Contact(); }
    public function owner(): Contact { return new Contact(); }
    public function __get(string $name): mixed { $loaded = new Contact(); $loaded->name = $name; return $loaded; }
}
```

<!-- example magic-properties -->
```csharp
// app/Stubs/Subscription.sharp
namespace App.Stubs;

import App.Subscription;

extern Subscription uses none;
```

<!-- example magic-properties: statements
Subscription subscription = new Subscription();
-->
```csharp
Contact contact = subscription.contact;              // compiles: the @property-read line types it
const owner = subscription.owner;                    // compiles: Any?
Function<Contact()> load = subscription.owner;       // compiles: the method, where a function is taken
Contact loaded = subscription.owner();               // compiles: calls the method
```
<!-- run magic-properties
contact.name;                // "contact"
(owner as Contact)?.name;    // "owner"
load().name;                 // "Ada"
loaded.name;                 // "Ada"
-->

A read typed `Any?` this way compiles with no warning, and an `as`, an `is` or a `match` states its type, as in `Contact? owner = subscription.owner as Contact;`. A write to such a property takes any value. A read after a write is still what `__get` gives back, so `row.count = "7"; return row.count;` reads `Any?`, not `string`.

A declared property the code can't read, such as an Eloquent model's protected `table` or `hidden`, is the property `__get` reads, as plain PHP reads it from outside the class.

**A `.sharp` class whose plain PHP parent defines `__get` or `__set` reaches a property they serve only through an `extern` property,** or through a declaration of the library, as a real property or an `@property` line. A read or a write of any other name is a compile error that names the `extern` property to write, so a misspelled column never compiles: "`Booking` has no property `guest`. Declare it: `public extern … guest { get; }`." A write names the type of the value it writes, as in `public extern string emial { get; set; }`.

<!-- example magic-property-models -->
```php
/** @property-read Contact $host */
abstract class Model
{
    public function __get(string $name): mixed { $loaded = new Contact(); $loaded->name = $name; return $loaded; }
}
```

<!-- example magic-property-models -->
```csharp
// app/Booking.sharp
namespace App;

public class Booking : Model
{
    public string hostName() => this.host.name;                        // compiles: the @property-read line declares it
    public string guestName() => (this.guest as Contact)?.name ?? "";   // compile error: `Booking` has no property `guest`. Declare it: `public extern … guest { get; }`.
}
```

**A `.sharp` class states the type of a property its plain PHP parent's `__get` gives and `__set` stores with an `extern` property,** as in `public extern Collection<int, Product> products { get; }` or `public extern string email { get; set; }` on an Eloquent model. It holds no value and takes no modifier but its access, and its `set` may narrow its own access, as in `{ get; private set; }`. PHP declares no property for it, so a read reaches `__get` and a write reaches `__set`. The read has the declared type, and the value `__get` gives is checked against it where it enters PHP#, so a value of another class throws `TypeError` there. A write is checked against the declared type and passed to `__set` as it is: converting a value for storage is the parent's own job, as an Eloquent cast is. `[Key("total_cents")]` names the key `__get` and `__set` receive when it differs from the property's name. It may share its name with a method, as a model's relation `products()` and the relation `products` its `__get` loads do, the one exception to the rule that one name declares one member. A class that inherits no `__get` has no such property, and one that inherits no `__set` has none with `set`, so either is a compile error.

<!-- example extern-properties -->
```php
abstract class Catalogue
{
    private array $stored = [];

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->stored)) {
            return $this->stored[$name];
        }
        $loaded = new Contact();
        $loaded->name = $name;

        return $loaded;
    }

    public function __set(string $name, mixed $value): void { $this->stored[$name] = $value; }
}
```

<!-- example extern-properties -->
```csharp
// app/Listing.sharp
namespace App;

public class Listing : Catalogue
{
    public extern Contact seller { get; }

    [Key("price_cents")]
    public extern int priceCents { get; set; }

    public Contact seller() => new Contact();

    public string sellerName() => this.seller.name;      // compiles: the extern property types the read __get gives

    public int reprice(int cents)
    {
        this.priceCents = cents;                         // compiles: __set stores it under "price_cents"
        return this.priceCents;
    }
}
```
<!-- run extern-properties
new Listing().sellerName();    // "seller"
new Listing().seller().name;   // "Ada"
new Listing().reprice(900);    // 900
-->

<!-- example extern-property-errors -->
```csharp
// app/Banner.sharp
namespace App;

public class Banner
{
    public extern Contact seller { get; }               // compile error: `Banner.seller` is `extern`, the property a plain PHP `__get` gives, and `Banner` inherits no `__get`.
}
```

<!-- example extern-property-shapes -->
```php
abstract class Gallery
{
    public function __get(string $name): mixed { $loaded = new Contact(); $loaded->name = $name; return $loaded; }
}
```

<!-- example extern-property-shapes -->
```csharp
// app/Auction.sharp
namespace App;

public class Auction : Gallery
{
    public extern Contact buyer { get; set; }           // compile error: `Auction.buyer` has `set`, which a plain PHP `__set` stores, and `Auction` inherits no `__set`.
}
```

**A property a class keeps in memory where its plain PHP parent stores every other one is a compile error,** unless it is private. On a class whose parent defines `__get` and `__set`, as an Eloquent model's does, a property that isn't `extern` holds its value in the object and never reaches the parent's storage, so it would never be saved. The error names both fixes: "`Invoice`'s parent `Ledger` stores properties through `__get` and `__set`, so a public `number` would never be saved." A property the parent declares itself, as Eloquent's `table`, stays the parent's.

<!-- example stored-properties -->
```php
abstract class Ledger
{
    private array $stored = [];

    public function __get(string $name): mixed { return $this->stored[$name] ?? null; }
    public function __set(string $name, mixed $value): void { $this->stored[$name] = $value; }
}
```

<!-- example stored-properties -->
```csharp
// app/Invoice.sharp
namespace App;

public class Invoice : Ledger
{
    public extern string reference { get; set; }        // compiles: __set stores it
    private int printed = 0;                            // compiles: the class's own memory
    public string number { get; set; } = "";            // compile error: `Invoice`'s parent `Ledger` stores properties through `__get` and `__set`, so a public `number` would never be saved.
}
```

**A field or property holding a `Function` is called like a method** when the class has no method with that name. A `.sharp` class cannot declare a method and a field or property with the same name, or inherit one from a `.sharp` class and declare the other, so `this.discount(…)` always has one meaning. An `extern` property is the exception, since PHP reads it through `__get` and never calls it. A plain PHP class may declare both, as Eloquent's `Model` declares the field `$casts` and the method `casts()`. PHP runs a call of the name as the method and a read as the field, PHP# code does too, and a `.sharp` class below it overrides either one.

<!-- example function-fields: statements
int price = 900;
-->
```csharp
private Function<int(int)> discount = (int amount) => amount - 100;
int paid = this.discount(price);       // calls the function in discount, since there is no discount method
```

The field or property is read before the arguments run. An argument that replaces `discount` doesn't change which function runs.

### 14.4 Null operators

- **`a ?? b`:** `b` when `a` is null.
- **`a ??= b`:** assigns `b` only when `a` is null.
- **`a?.b`:** reads `b`, or gives null when `a` is null. PHP writes this as `?->`. A null `a` skips the rest of the chain, so in `a?.items[0].name` only `a` may be null, and a second `?.` on a link that is never null warns.
- **`a?.b = c`:** writes `b` only when `a` is not null, as `=`, every compound assignment, `++`, `--` and an index write such as `a?.tags["vip"] = true` do.
- **`f?.(x)`:** calls `f` only when it is not null.
- **`a ?? throw …`, `a ?? return`, `a ?? continue` and `a ?? break`:** leave when `a` is null. `return` takes a value when the method returns one.

<!-- example leave-on-null: members
private Map<string, int> prices = [:];
private Map<int, Customer> customers = [:];
private void charge(Customer customer, int price) { }
-->
```csharp
public void renewAll(List<int> customerIds, string plan)
{
    int price = this.prices[plan] ?? return;                // no such plan: nothing to renew
    for (const id of customerIds) {
        Customer customer = this.customers[id] ?? continue; // skip ids with no customer
        this.charge(customer, price);
    }
}
```

**The rest of the chain reads a `?.` receiver as not null,** because it runs only when the receiver is not null. An argument, an index, or the value a write stores after `step?.` uses `step` with no second `?.`. After the chain, `step` may be null again, whatever the skipped part tested or assigned.

<!-- example null-safe-rest-of-chain: statements
public class Router { public string route(string label) => label; }
public class Step { public string title { get; set; } = ""; public Router router { get; } = new Router(); public List<string> labels { get; set; } = []; public string label() => this.title; public int last() => 0; public string route(string label) => label; }
public class Steps { public Step? find(int id) => id == 1 ? new Step() : null; }
private Steps steps = new Steps();
-->
```csharp
Step? step = this.steps.find(1);                    // null when no step has this id
string? routed = step?.route(step.label());         // compiles: step is not null in the arguments
string? passed = step?.router.route(step.label());  // compiles: nor further along the chain
string? named = step?.labels[step.last()];          // compiles: nor in an index
step?.title = step.label();                         // compiles: nor in the value a write stores
string? titled = step?.route(step.title);           // compiles: nor when the argument reads a property
string title = step.title;                          // compile error: after the chain, step may be null
```

**A `?.` receiver that is a property is read again in the rest of the chain, at its declared type,** because a property never narrows (section 21). Read it into a local first, which the chain then reads as not null.

<!-- example null-safe-property-receiver: statements
public class Step { public string label() => "step"; public string route(string label) => label; }
private Step? current = null;
-->
```csharp
string? again = this.current?.route(this.current.label()); // compile error: this.current is read again, as Step?
Step? step = this.current;                                 // one read, into a local
string? routed = step?.route(step.label());                // compiles: step is not null in the arguments
```

**A write through `?.` happens only when every `?.` receiver on its left is not null.** When one is null, nothing is written, and neither the right side nor a `set` body runs. Each receiver runs once, and the write goes to the object it tested, so `this.orders.find(id)?.note = "gift"` calls `find` once.

**An index into a collection that may be null is an error,** read or written, as a member read on a value that may be null is. Test the collection first, as in `if (tags != null)`.

<!-- example null-safe-writes: statements
public class Customer { public string name { get; set; } = ""; }
public class Item { }
public class Order { public string note { get; set; } = ""; public List<Item> items { get; set; } = []; public int total { get; set; } = 0; public Customer customer { get; } = new Customer(); }
public class Orders { public Order? find(int id) => id == 1 ? new Order() : null; }
private Orders orders = new Orders();
private int fee() => 5;
int id = 1;
Item item = new Item();
-->
```csharp
Order? order = this.orders.find(id);                 // null when no order has this id
order?.note = "gift";                                // compiles: sets note when order exists, does nothing when it's null
order?.items.add(item);                              // compiles: already allowed today, a call through ?.
order?.total += this.fee();                          // compiles: fee() runs only when order exists
order?.customer.name = "Ada";                        // compiles: skips when order is null; customer is not nullable
```

**A `?` or a null check that cannot matter warns,** and the code compiles: a null check, `?.` or `??` on a value whose type has no `?`. Section 24 gives the rule for `?`. A nullable return type on a method that never returns null compiles with no warning.

<!-- example needless-null-checks: members
private Customer customer = new Customer();
-->
```csharp
public void renew(Customer customer, Plan plan)
{
    if (customer != null) { … }                     // compiles, and warns: customer is Customer, so it can never be null
    int price = plan.price ?? 0;                     // compiles, and warns: plan.price is int, so ?? never applies
}
public Customer? current() { return this.customer; } // compiles: a caller handles a null it never gets
```

## 15. Events

An event says that something happened, such as an order being paid. `emit` raises it, and every listener for it runs. Nothing is wired by hand.

<!-- example event-declarations -->
```csharp
// app/OrderEvent.sharp
namespace App;

public interface OrderEvent { int orderId { get; } }                  // the type both events share
```

<!-- example event-declarations -->
```csharp
// app/OrderPaid.sharp
namespace App;

public event OrderPaid(int orderId, Money amount) : OrderEvent;       // declares the event type OrderPaid
```

<!-- example event-declarations -->
```csharp
// app/OrderRefunded.sharp
namespace App;

public event OrderRefunded(int orderId, Money amount) : OrderEvent;   // declares the event type OrderRefunded
```

<!-- example event-declarations -->
```csharp
// app/Order.sharp
namespace App;

public class Order
{
    public Order(public int id { get; }, public Money total { get; }) { }

    public void markPaid()
    {
        …
        emit new OrderPaid(this.id, this.total);                      // runs every listener for OrderPaid
    }
}
```

<!-- example event-declarations -->
```csharp
// app/Receipts.sharp
namespace App;

public class Receipts
{
    public void deliver(OrderPaid e) on OrderPaid { … }               // listens to every OrderPaid
}
```

**Declaring:** `event` declares an event type. `public event OrderPaid(int orderId, Money amount);` declares `OrderPaid`, a class whose parameters become get-only properties.

**A parameter takes any type,** a collection too. A list literal passed for a `Set` parameter becomes a `Set`, as it does for a method's parameter (section 12):

<!-- example event-set-parameter -->
```csharp
// app/OrderTagged.sharp
namespace App;

public event OrderTagged(int orderId, Set<string> tags);
```

<!-- example event-set-parameter: statements -->
```csharp
const tagged = new OrderTagged(1, ["vip", "new", "vip"]);
tagged.tags.count();                 // 2: the literal became a Set, which holds "vip" once
```

**No type nests.** Every type, events and interfaces included, is declared at file level in its own file (section 27). An interface that several events share, such as `OrderEvent`, is declared the same way. An event lives in the namespace of the code it describes:

<!-- example standalone-event -->
```csharp
// app/Imports/RowImported.sharp
namespace App.Imports;

public event RowImported(int row);
```

**Emitting:** `emit` raises an event. Any code may raise any event type:

<!-- example emit-forms: statements
public event OrderPaid(int orderId, Money amount);
Order order = new Order();
Money total = new Money(500, "USD");
-->
```csharp
emit new OrderPaid(order.id, total);             // runs every listener for OrderPaid
```

**`new Order.Paid(…)` always calls `Order`'s named constructor `Paid`** (section 9.1). `.` in code is always member access, and a class can't share its full name with a namespace (section 23), so a type `App.Order.Paid` can't exist beside the class `App.Order`. The named constructor is the only reading.

**Plain PHP events:** `extern event` marks a plain PHP class, such as a Laravel event, as an event, so PHP# code can listen to it:

<!-- example extern-event -->
```csharp
// app/Stubs/Laravel.sharp
namespace App.Stubs;

import Illuminate.Queue.Events.JobFailed;

extern event JobFailed uses none;
```

**`extern event` states the class's effects after `uses`,** as every `extern` line does (section 29). `extern event JobFailed uses none;` declares an event that is pure. With effects after `uses`, a call into the event's class has the effects it names:

<!-- example extern-event-effects -->
```csharp
// app/Stubs/Laravel.sharp
namespace App.Stubs;

import Illuminate.Queue.Events.JobFailed;

extern event JobFailed uses Database;            // an event, and a call into JobFailed has the effect Database
```

**Listening:** a trailing `on` clause after a method's parameters lists the events the method listens to, for as long as the app runs. The method takes zero parameters, or one parameter whose type every listed event shares. It takes no other parameter, and no parameter is implicit. PHP# creates a new object for every event and passes its constructor's services from the module of its namespace. A listener's class needs no binding.

<!-- example listeners
import Illuminate.Queue.Events.JobFailed;
public interface OrderEvent { int orderId { get; } }
public event OrderPaid(int orderId, Money amount) : OrderEvent;
public event OrderRefunded(int orderId, Money amount) : OrderEvent;
-->
```csharp
public class OrderNotices
{
    public void send(OrderEvent e) on OrderPaid, OrderRefunded { … }      // two events, read through the type they share
    public void refresh() on OrderPaid { … }                              // no parameter: the event's data is not needed
    public void audit(OrderEvent e) on OrderEvent { … }                   // every event that implements OrderEvent
    public void alert(JobFailed e) on JobFailed { … }                     // a plain PHP event marked with extern event
}
```

- **A listener on an interface hears every event that implements it.**
- **Every `on` is checked before the code runs.** A wrong event name, or a parameter type the listed events don't share, is a compile error:

<!-- example listener-errors: members
public event OrderPaid(int orderId, Money amount);
public event OrderRefunded(int orderId, Money amount);
-->
```csharp
public void send(OrderPaid e) on OrderPaid, OrderRefunded { … }         // compile error: OrderRefunded is not an OrderPaid
public void refresh() on OrderPayed { … }                               // compile error: there is no event OrderPayed
public void log(OrderPaid e, Logger logger) on OrderPaid { … }          // compile error: a listener takes only the event
```

**A generic event declares type parameters as a class does.** `emit new Saved<Order>(order)` raises a `Saved<Order>`, and an `on` clause names the type arguments it hears, as every type names them (section 11):

- **`on Saved<Order>` hears only a `Saved<Order>`,** never a `Saved<Invoice>`. `Events.on<Saved<Order>>` hears the same.
- **A bare `on Saved` is a compile error** that says to name the type arguments.
- **`on Saved<T>` with a type parameter is a compile error,** since a listener runs for one type its class names, so it names the type, as in `on Saved<Order>`, or listens on an interface the event implements.
- **A listener on an interface the event implements hears every `Saved<…>`.**

<!-- example generic-events -->
```csharp
// app/Saving/EntityEvent.sharp
namespace App.Saving;

public interface EntityEvent { }
```

<!-- example generic-events -->
```csharp
// app/Saving/Saved.sharp
namespace App.Saving;

import App.DatabaseEntity;

public event Saved<T : DatabaseEntity>(T entity) : EntityEvent;     // declares the generic event Saved
```

<!-- example generic-events -->
```csharp
// app/Saving/Invoice.sharp
namespace App.Saving;

import App.DatabaseEntity;

public class Invoice : DatabaseEntity
{
    public void save() { emit new Saved<Invoice>(this); }           // runs the listeners for Saved<Invoice> and for EntityEvent
}
```

<!-- example generic-events -->
```csharp
// app/Saving/Audit.sharp
namespace App.Saving;

import App.Order;

public class Audit
{
    public void deliver(Saved<Order> e) on Saved<Order> { … }       // hears a Saved<Order> only
    public void record(EntityEvent e) on EntityEvent { … }          // hears every Saved<…>
    public void any(Saved<Order> e) on Saved { … }                  // compile error: name Saved's type argument, as in Saved<Order>
}
```

<!-- example generic-events -->
```csharp
// app/Saving/Tracker.sharp
namespace App.Saving;

import App.DatabaseEntity;

public class Tracker<T : DatabaseEntity>
{
    public void heard(Saved<T> e) on Saved<T> { … }                 // compile error: name the type it hears, as in Saved<Order>
}
```

**Listening for a while:** `Events.on<RowImported>(e => …)` starts a listener and returns a handle. The listener stops when the handle goes out of scope:

<!-- example scoped-listener: members
public class ProgressBar { public void advance() { } }
public class Importer { public void run(string path) { } }
-->
```csharp
import App.Imports.RowImported;

public class ImportScreen
{
    public ImportScreen(private ProgressBar progress, private Importer importer) { }

    public void import(string path)
    {
        const listening = Events.on<RowImported>(e => this.progress.advance());   // listens until import returns
        this.importer.run(path);
    }
}
```

**Effects:** `emit` has the effect `Events` (section 29), so a method that emits shows `Events` among its effects, and code without a body allows it with `uses Events`.

**Timing:** the default dispatcher runs listeners immediately, before `emit` returns. An app replaces the dispatcher once at startup, to run listeners later, after a save, with retries, or not at all in tests.

The app's top module binds `Dispatcher`, its binding serves every `emit`, and a binding of it in any other module of the app is a compile error. A test module still replaces it:

<!-- example one-dispatcher
namespace App;

import Sharp.Dispatcher;
import Sharp.ImmediateDispatcher;
-->
```csharp
// app/Module.sharp
module
{
    singleton Dispatcher = ImmediateDispatcher;   // compiles: the app's dispatcher, for every emit
}
```

<!-- example one-dispatcher
namespace App.Shop;

import Sharp.Dispatcher;
import Sharp.ImmediateDispatcher;
-->
```csharp
// app/Shop/Module.sharp
module
{
    singleton Dispatcher = ImmediateDispatcher;   // compile error: only app/Module.sharp, the top module, binds Dispatcher
}
```

**A plain PHP library's own events reach PHP# listeners through PSR-14.** PHP#'s `Sharp.Psr.ListenerProvider` offers every PHP# listener as a PSR-14 listener provider. One module binding gives it to a library whose dispatcher follows PSR-14, as any binding creates a service (section 32):

<!-- example psr-listeners -->
```csharp
// app/Module.sharp
namespace App;

import Psr.EventDispatcher.EventDispatcherInterface;
import Sharp.Psr.ListenerProvider;
import Vendor.Events.EventDispatcher;

module
{
    singleton EventDispatcherInterface = new EventDispatcher(new ListenerProvider());   // compiles: the library's dispatcher runs every PHP# listener
}
```

- **Every event the library dispatches runs each `on` method and each listener `Events.on` started for it,** with nothing else written by hand. Each listener runs on a new object, as for `emit`, and the library's dispatcher decides when.
- **A library with a dispatcher of its own kind needs one adapter package,** and PHP# ships none for any framework.

## 16. Naming a value

Every place that names a value uses `name: value`:

- named arguments
- attribute arguments
- object initializers
- `with`

<!-- example naming-values: ignore an attribute with no declaration to attach to -->
```csharp
new Money { amount: 500, currency: "USD" }
price with { amount: 400 }
new Artifact.fromJson(json: raw)
[Field(label: "Name")]
```

When a variable has the same name as the property, the value can be left out:

<!-- example initializer-shorthand -->
```csharp
// app/Amount.sharp
namespace App;

public struct Amount
{
    public int value { get; init; }
    public string currency { get; init; }
}
```

<!-- example initializer-shorthand: statements
Account account = new Account();
-->
```csharp
const currency = account.currency;
Amount price = new Amount { value: 500, currency };     // short for currency: currency
```

**Any parameter can be named at a call,** including a call into plain PHP. An override keeps every parameter name of the method it overrides (section 22), so a name means the same parameter on every class.

**A named argument that matches no other parameter of plain PHP fills its `...` parameter under its name,** as PHP collects it, on a method, a static method, a constructor, a function and an attribute. The `...` parameter's element type checks each value, as it checks a positional one. A PHP# `...` parameter is a `List` (section 7), which has no names, and a function built into PHP takes its `...` values by position, so a named argument that matches no parameter there is a compile error. Plain PHP that passes names to a PHP# `...` parameter, an override's included, throws on entry, with "`Wrapper.wrap` takes `...attributes` by position, and was given `class:` and `id:`.", so no name or value is lost.

<!-- example named-variadics -->
```php
#[\Attribute(\Attribute::TARGET_ALL)]
final class Generic { public function __construct(string ...$types) {} }

final class Html
{
    public static function tag(string $name, string ...$attributes): string { return $name . ' ' . implode(',', array_keys($attributes)); }
}
```

<!-- example named-variadics -->
```csharp
// app/Stubs/Html.sharp
namespace App.Stubs;

import App.Html;

extern Html uses none;
```

<!-- example named-variadics -->
```csharp
// app/Catalog.sharp
namespace App;

[Generic(Item: typeof(DatabaseEntity))]                        // compiles: Generic(string ...$types) receives ["Item" => DatabaseEntity]
public class Catalog<TItem : DatabaseEntity>
{
    public string home() => Html.tag("a", href: "/home", title: "Home");   // compiles: tag(string $name, string ...$attributes) receives ["href" => "/home", "title" => "Home"]

    public static int sum(int ...values) => values.count();

    public int total() => Catalog<TItem>.sum(first: 1, second: 2);          // compile error: sum takes its ...values by position
}
```

<!-- example named-arguments: statements
Image image = new Image();
string url = "https://example.com/orders";
Map<string, string> payload = ["id": "1"];
-->
```csharp
import Illuminate.Support.Facades.Http;

image.resize(width: 800, height: 600);
Http.post(url, data: payload);           // plain PHP: Laravel's post(string $url, $data = [])
```

An object initializer runs after the constructor. It sets properties through their `set` or `init` accessors, so validation in those accessors runs. The checker requires every `required` property to be set.

## 17. Loops

`for … of` loops over a collection or any `Iterable<T>` (section 12). The loop variable is declared with `const` or `let`, and each pass gets a fresh variable (section 3).

<!-- example for-of: statements
List<Line> lines = [new Line()];
Map<string, Plan> plans = ["pro": new Plan()];
-->
```csharp
for (const line of lines) { … }
for (const [key, plan] of plans) { … }           // each entry of a Map is a [key, value] tuple
for (const entry of plans) { … }                 // entry is a [string, Plan] tuple
for (const [i, line] of lines.entries()) { … }
```

<!-- example map-values: statements
Map<string, Plan> plans = ["pro": new Plan()];
-->
```csharp
for (const plan of plans.values()) { … }         // a Map's values
```

**A loop over a `Map` gives each entry as a `[key, value]` tuple, and `values()` gives the values.** The key arrives as the key type, as in `for (const [status, n] of counts)`, where `status` arrives as a `Status`. Writing the type stays allowed (section 12).

**A `const` loop variable can have its type written,** as in `for (const [Status status, int n] of counts)`. `let` with a written type is a parse error, because a typed local is written without `let` (section 2). A written `string` key over a `Map<string, TValue>` reads back as a `string`.

<!-- example typed-loop-variables: statements
Map<Status, int> counts = [Status.Paid: 1];
List<Line> lines = [new Line()];
-->
```csharp
for (const [Status status, int n] of counts) { … }   // compiles: counts is Map<Status, int>
for (const Line line of lines) { … }                 // compiles
for (let [Status status, int n] of counts) { … }     // parse error: let takes no written type
```

**A loop reads an `Iterable<T>` alone,** so a loop over any other value is a compile error that names its type. That holds for an object of a class that is no `Iterable` (section 12), where PHP would loop over its public properties:

<!-- example loop-over-object -->
```csharp
// app/Basket.sharp
namespace App;

public class Basket
{
    private List<string> items = ["tea"];

    public int count()
    {
        let n = 0;
        for (const item of this.items) { n += item.length(); }   // compiles: a List<string> is an Iterable<string>
        for (const item of this) { n += 1; }                     // compile error: Basket is no Iterable
        return n;
    }
}
```

`for (x in y)` is no PHP# syntax, so it is a parse error, as any form outside PHP# is (section 1).

These keep their C and PHP form:

- `while (…) { … }`
- `do { … } while (…);`
- `for (let i = 0; i < n; i++) { … }`

PHP's `foreach` is removed.

**A loop can take a name, as `orders: for (…)`, which only `break` and `continue` read.** `break orders;` leaves the loop named `orders` from a loop inside it, and `continue orders;` goes on with that loop's next pass. The name is optional, and a plain `break` or `continue` means the innermost loop. A loop name that no `break` or `continue` names is a compile error. So is a name a loop around it already has, and a `break` or `continue` that names no loop around it. A count after `break` or `continue`, as `break 2`, is a parse error, since the name says which loop it leaves.

<!-- example loop-names: statements
List<List<int>> orders = [[1, 2], [3, 0, 4], [5, -1, 6], [7, 99]];
let total = 0;
-->
```csharp
orders: for (const order of orders) {        // compiles: an optional loop name
    for (const line of order) {
        if (line == 0) { continue orders; }      // continues the outer loop
        if (line > 50) { break orders; }         // leaves the outer loop
        if (line < 0) { break; }                 // a plain break leaves the innermost loop
        total += line;
    }
}
total;                                           // 18
```

<!-- example loop-name-limits: statements
bool running = true;
-->
```csharp
unused: while (running) { running = false; }   // compile error: no break or continue names the loop unused
while (running) { break 2; }                   // parse error: break takes a loop's name, not a count
```

**`?? continue` in a loop's own condition skips to the loop's next check,** as `continue` does anywhere in the loop (section 14.4). The condition runs again, so the body runs only for a value that is not null, and `?? break` ends the loop at the first null instead. A source that stays null keeps the loop running, as `while (true) { continue; }` does.

<!-- example continue-in-condition: statements in Feed
private int calls = 0;
private bool? next() { this.calls += 1; return this.calls % 3 == 0 ? null : this.calls < 7; }
let passes = 0;
-->
```csharp
while (this.next() ?? continue) { passes += 1; }                // a null runs the condition again
do { passes += 1; } while (this.next() ?? continue);
for (let i = 0; this.next() ?? continue; i++) { passes += 1; }  // a null runs i++, then the condition
passes;                                                          // 5
```

**The body of every `if`, `else`, `while`, `do` and `for` is written in braces,** as PSR-12 requires of PHP. A body without braces is a compile error:

<!-- example braces: statements
bool done = false;
-->
```csharp
if (done) { return; }           // compiles
if (done) return;               // compile error: write the body in braces, as in { return; }
while (!done) done = true;      // compile error: write the body in braces, as in { done = true; }
```

## 18. Strings

**Joining:** `+` joins strings. Joining a string with a number is a compile error, so `"1" + 1` cannot produce `"11"`. `+` in plain PHP files keeps its PHP meaning.

What `+` does is chosen from the operand types the checker sees. Two strings join, two numbers add, and an object with `operator +` (section 19) calls it.

<!-- example string-joining: statements
Order order = new Order();
-->
```csharp
const label = "Order " + order.number;
```

**Interpolation:** backtick templates with `${expr}` interpolate. They can span several lines, so they replace heredoc and nowdoc.

<!-- example templates: statements
Order order = new Order();
Contact contact = new Contact();
-->
```csharp
const label = `Order ${order.number} for ${contact.name}`;
const html = `
    <div class="order">${order.number}</div>
`;
```

A template's `${expr}` accepts `int`, `float`, `string`, `bool`, and any type that declares `string toString()`. A `bool` prints `true` or `false`, and any other type prints what its `toString()` returns. Every other type is a compile error, "A template shows `int`, `float`, `string`, `bool` or a type that declares `toString()`, and `Status` is none of them.", with the offending type's name in place of `Status`. A literal or narrowed type counts as its base type, so `1|2` is an `int`. A union of `int`, `float`, `string` and `bool`, such as `int|string`, is taken, and each value prints as its own type does. A nullable value such as `int?` is refused until it is checked (section 24), and the same error names `int?`.

<!-- example template-types: statements
int count = 3;
bool isPaid = true;
Status status = Status.Paid;
List<int> items = [1, 2];
Order order = new Order();
-->
```csharp
const a = `Total: ${count}`;   // int: "Total: 3"
const b = `Paid: ${isPaid}`;   // bool: "Paid: true"
const c = `Status: ${status}`; // compile error: Status declares no toString()
const d = `Items: ${items}`;   // compile error: List<int> declares no toString()
const e = `Order: ${order}`;   // compile error: Order declares no toString()
```

<!-- example template-to-string -->
```csharp
// app/Sku.sharp
namespace App;

public class Sku
{
    public Sku(public string code { get; }) { }
    public string toString() => this.code;
}
```

<!-- example template-to-string: statements -->
```csharp
const sku = new Sku("A-12");
const label = `Sku: ${sku}`;   // Sku declares toString(): "Sku: A-12"
```

A plain PHP file keeps PHP's own interpolation. It converts an object of a class that declares `toString()`, or takes it from an interface's default body (section 22), to the text a template shows, as `(string) $sku` and `"Sku: {$sku}"` do.

**A plain PHP class's `__toString()` is its `toString()` in PHP# code,** its own or inherited, when it declares and inherits no `toString()`. A template shows it and `email.toString()` calls it, and both run PHP's `__toString()`. PHP# code never names `__toString`, and a plain PHP class with neither method stays a template's compile error:

<!-- example plain-to-string -->
```php
final class Email
{
    public function __construct(private string $address) {}

    public function __toString(): string { return $this->address; }
}

final class Row {}
```

<!-- example plain-to-string -->
```csharp
// app/Stubs/Email.sharp
namespace App.Stubs;

import App.Email;
import App.Row;

extern Email uses none;
extern Row uses none;
```

<!-- example plain-to-string: statements
Email email = new Email("ada@example.com");
Row row = new Row();
-->
```csharp
`To: ${email}`;                        // "To: ada@example.com"
email.toString();                      // "ada@example.com"
`Row: ${row}`;                         // compile error: Row declares neither toString() nor __toString()
```

**A `toString()` is always `string toString()`:** an instance method with no required parameter that returns `string`. A template and every conversion to text call it with no arguments, so any other `toString()` a type declares is a compile error that names the signature to write, "`Sku.toString()` returns `int`, and PHP# shows a value as text by the `string` its `toString()` returns.", and so is a template of a plain PHP class whose `toString()` is not that one.

**Plain strings:** `"…"` and `'…'` never interpolate, so braces and `$` inside them are literal.

**Text methods:** a `string` has text methods, from the standard library's extensions on `string` (section 23), so PHP# code never calls PHP's string functions (section 8). They work on characters, never bytes, so `length` counts characters and a case change reaches every letter. `substring`, `indexOf` and `lastIndexOf` count in characters, a search that finds nothing gives `null`, and a range or a start outside the text throws `OutOfRangeException`. `trim`, `trimStart` and `trimEnd` remove whitespace, or each character of the text they are given, and `removePrefix` and `removeSuffix` remove their text once when the text starts or ends with it. `replace` replaces every match. `split` keeps the empty text after a trailing delimiter, and takes a `limit`: 0, its default, gives every part, and a positive limit gives at most that many, the last holding the rest. `lines` splits at `"\r\n"`, `"\n"` and `"\r"`. Empty text to replace and an empty delimiter match before each character and after the last. A negative limit, empty padding and a negative count each throw `ValueError`. `isEmpty()` and `isNotEmpty()` tell whether the text holds a character, as they tell whether a collection holds an element (section 12), so `"0"` is not empty:

<!-- example text-methods: statements -->
```csharp
"école".length();                         // 5
"".isEmpty();                             // true
"0".isNotEmpty();                         // true
"école".substring(1, 3);                  // "co"
"banana".indexOf("a", 2);                 // 3
"banana".indexOf("x");                    // null
"banana".lastIndexOf("a");                // 5
"/path/".removeSuffix("/");               // "/path"
"7".padStart(3, "0");                     // "007"
"école".capitalized();                    // "École"
"ab".repeat(3);                           // "ababab"
"a\nb".lines();                           // ["a", "b"]
"éa".characters();                        // ["é", "a"]
"éa".codePoints();                        // [233, 97]
"abc".substring(4);                       // throws OutOfRangeException
"hello".contains("ell");                  // true
"hello".contains("xyz");                  // false
"ÉCOLE".lowercase();                      // "école"
"école".uppercase();                      // "ÉCOLE"
"hello".startsWith("he");                 // true
"hello".startsWith("lo");                 // false
"hello".endsWith("lo");                   // true
"hello".endsWith("he");                   // false
"banana".replace("a", "o");               // "bonono"
"ab".replace("", "-");                    // "-a-b-"
"a,b,".split(",");                        // ["a", "b", ""]
"a:b:c".split(":", 2);                    // ["a", "b:c"]
"a,b".split("");                          // ["", "a", ",", "b", ""]
"a:b".split(":", -1);                     // throws: "`split` takes a limit of 0 or more, and it is negative."
"hello".contains(1);                      // compile error: contains takes a string
str_contains("hello", "ell");             // compile error: no function str_contains exists
```

**Ignoring case:** `compareTo`, `equals`, `indexOf`, `lastIndexOf`, `contains`, `startsWith` and `endsWith` take `ignoreCase`, `false` by default. It compares each character folded by Unicode's simple case folding, so an index stays an index into the text. `titlecase()` capitalizes each word, `replace` takes a `limit` of how many matches it replaces, a code point's `toCharacter()` gives its character, a `List<int>`'s `toBytes()` gives `Bytes` of its values, and `normalized()` gives the text in a Unicode normalization form, `NormalizationForm.C` by default. `isLetter()`, `isDigit()`, `isLetterOrDigit()`, `isWhitespace()`, `isUppercase()` and `isLowercase()` are true when the text holds a character and each one is in the class. A code point that is no Unicode scalar value and a byte outside 0 to 255 throw `ValueError`, and a plain PHP value that becomes a `string` has each sequence that is not UTF-8 replaced by U+FFFD:

<!-- example text-case-and-classes: statements
import Sharp.Text.NormalizationForm;
-->
```csharp
"Apple".equals("APPLE", ignoreCase: true);        // true
"Zebra".compareTo("apple", ignoreCase: true);     // 1
"Hello hello".lastIndexOf("HELLO", true);         // 6
"hello world".titlecase();                        // "Hello World"
"banana".replace("a", "o", 1);                    // "bonana"
0x1F600.toCharacter();                            // "😀"
[104, 105].toBytes().toText();                   // "hi"
"Ωmega".isLetter();                               // true
"".isLetter();                                    // false
"é".normalized(NormalizationForm.D).length();     // 2: "e" and a combining accent
0xD800.toCharacter();                             // throws ValueError
```

**Text and bytes:** a `string` is text and always valid UTF-8. Binary data, as `Ip.toBinary` gives, and the bytes another system counts or signs are `Bytes`, a separate type. A plain PHP value that becomes a `string` has each byte sequence that is not UTF-8 replaced by U+FFFD, and one that becomes `Bytes` keeps every byte. `text.toBytes()` gives a string's UTF-8 bytes, and `bytes.toText()` reads bytes as UTF-8, replacing each sequence that is not UTF-8 by U+FFFD. `Bytes` has `length()`, `slice(start, end)`, which throws `OutOfRangeException` for a range outside the bytes, `toList()`, which gives each byte as an `int` from 0 to 255, and `a + b`, which gives the bytes of `a` followed by those of `b` and is the one way to join two `Bytes`. A string literal is text too: one whose escapes write bytes that are no UTF-8 is a compile error that writes those bytes as `Bytes`, and `\u{E9}` writes a character's UTF-8 bytes:

<!-- example text-bytes: statements
import Sharp.Net.Ip;
-->
```csharp
"école".length();                          // 5
"école".toBytes().length();                // 6
"école".toBytes().toList();                // [195, 169, 99, 111, 108, 101]
"école".toBytes().slice(0, 2).toText();    // "é"
"école".toBytes().slice(0, 9);             // throws: "`slice` takes a range inside the bytes, and the range is outside it."
([1, 2].toBytes() + [3].toBytes()).toList();   // [1, 2, 3]
Ip.toBinary("192.168.0.1").toList();       // [192, 168, 0, 1]
Ip.toBinary("::1").length();               // 16
"\xB1\x31";                                // compile error: this literal's escapes are no UTF-8 text; bytes are [0xB1, 0x31].toBytes()
"caf\u{E9}".length();                      // 4
"--sku--".trim("-");                       // "sku"
"/path/".trimEnd("/");                     // "/path"
"xyx-a-xyx".trim("xy");                    // "-a-": each character of "xy", not the text "xy"
```

PHP's backtick shell execution is removed, and so is `shell_exec()`. A program runs through `Process.run(program, arguments)` (section 29), which takes its arguments as a list, so no shell reads them.

## 19. Equality, comparison and operators

**`==` exists only where the type has it:**

- **Strings, numbers, enums and class values** (section 25) compare by value with `==`, with no code.
- **A struct or a collection gets `==` with no code when every field or element has `==`,** and it compares each field or element with that one's own `==`. A struct with a field that has no `==` has no `==` either.
- **Any type may declare `operator ==` with `hash()`,** a struct as well as a class, and the declared one replaces the one a struct gets with no code.
- **A class** compares with `==` only if it or a parent declares `operator ==`. Otherwise `==` on it is a compile error.
- **A function has no `==`,** so `==` between two function values is a compile error.
- **A type parameter has `==` only through its bound:** `Equatable`, or a class or interface that declares or inherits `operator ==` (section 11).
- **`Any?`** compared with `==` against a struct, enum, string, number or collection compares by value, and is false when the types differ.
- **`===`** is true only for the same object. It works on classes only, and cannot be overridden.
- **`Set`** hashes a struct, enum, string, number or collection by its value, and an object by its class's `hash()`. A struct that declares `hash()` is hashed by it.

**Two sets are `==` when they hold the same elements, in any order.** Two equal sets hash alike whatever their order, so a `Set` keys a `Map` and joins a `Set`. A literal takes its collection type from where it lands (section 12), so a list literal compared with a set is a set:

<!-- example set-equality: statements -->
```csharp
Set<int> a = [1, 2];
Set<int> b = [2, 1];
Set<int> c = [1, 2, 3];
a == b;                              // true: the same elements, in any order
a == c;                              // false
a == [2, 1];                         // true: the literal is a Set
a == [];                             // false: the empty Set holds no element
Map<Set<int>, string> names = [a: "pair"];
names[b] ?? "none";                  // "pair": b hashes as a does
```

**A union that holds a collection beside another type compares by the value it holds,** as each of its types compares:

<!-- example union-equality: statements -->
```csharp
Set<int>|int either = [2, 1];
Set<int> pair = [1, 2];
either == pair;                      // true: either holds a Set of the same elements
either == 1;                         // false: either holds a Set, not an int
pair == 1;                           // compile error: a Set never equals an int
```

**Operators** are declared once on a type and inherited by its subclasses:

<!-- example operators -->
```csharp
// app/DatabaseEntity.sharp
namespace App;

public abstract class DatabaseEntity
{
    public int id { get; }
    public DatabaseEntity(int id) { this.id = id; }
    public static bool operator ==(DatabaseEntity a, DatabaseEntity b) => a.id == b.id;
    public int hash() => this.id;
}
```

<!-- example operators -->
```csharp
// app/Money.sharp
namespace App;

public class Money
{
    public static bool operator ==(Money a, Money b) { … }   // a and b are never null
    public int hash() { … }
    public static int operator <=>(Money a, Money b) { … }
    public static Money operator +(Money a, Money b) { … }
}
```

<!-- example operators: statements
public class Order : DatabaseEntity { public Order(int id) : super(id) { } }
Order order = new Order(1);
Order sameOrderLoadedAgain = new Order(1);
Cart cart = new Cart();
Cart otherCart = new Cart();
-->
```csharp
order == sameOrderLoadedAgain;   // Order inherits DatabaseEntity's ==, so this compares ids
cart == otherCart;               // compile error: Cart declares no ==
cart === cart;                   // true: the same object
```

A struct declares `==` the same way, and replaces the one it gets with no code:

<!-- example struct-equality -->
```csharp
// app/Email.sharp
namespace App;

public struct Email
{
    public string address { get; init; }
    public static bool operator ==(Email a, Email b) => a.address.trim() == b.address.trim();
    public int hash() { … }
}
```

<!-- example struct-equality: statements -->
```csharp
Email typed = new Email { address: "ada@example.com " };
Email stored = new Email { address: "ada@example.com" };
typed == stored;                 // true: Email's own == ignores the trailing space
const twice = (int x) => x * 2;
twice == twice;                  // compile error: a function has no ==
```

- **Overloadable operators:** `+ - * / % **`, unary `-`, `==` and `<=>`, and indexing through an indexer (section 6.6).
- **A class, a struct, an enum and an interface declare operators** by the same rules. An interface writes `static` alone, as it writes every member bare, and its operator applies to every implementer (section 22).
- **An operator is chosen from the types of both sides.** Of the operators the two types and their interfaces declare, the most specific one that takes both sides runs, whichever side comes first. Two that take both, where neither is more specific, are a compile error at the use. `sortedBy` orders as `<` on two of its keys does.
- **`!=`** is derived from `==`.
- **`< > <= >=` and sorting** are derived from `<=>`.
- **Strings compare byte by byte.** `<`, `>` and `sort` put `"10"` before `"9"` and capitals before lowercase, so `"10" < "9"` is true and `"Zebra" < "apple"` is true. The order agrees with `==`. `compareTo` gives -1, 0 or 1 in the same order, so `"b".compareTo("a")` is 1.
- **`==` and `hash()` go together:** declaring `==` without `hash()` is a compile error, because `Set` needs both.

An enum orders its cases only when it declares `<=>`:

<!-- example enum-operators -->
```csharp
public enum Size : int
{
    case Small = 1;
    case Large = 3;
    public static int operator <=>(Size a, Size b) => a.value <=> b.value;   // compiles: Size.Small < Size.Large is true
}
```

<!-- example enum-operators: statements -->
```csharp
Size.Small < Size.Large;                  // true
Size.Large <=> Size.Small;                // 1
Status.Active < Status.Paused;            // compile error: Status declares no <=>, so its cases have no order
```

Two interfaces that each declare an operator for a class make it pick one:

<!-- example operator-choice -->
```csharp
// app/Weighed.sharp
namespace App;

public interface Weighed
{
    int grams { get; }
    int hash();
    static bool operator ==(Weighed a, Weighed b) => a.grams == b.grams;
}
```

<!-- example operator-choice -->
```csharp
// app/Valued.sharp
namespace App;

public interface Valued
{
    int cents { get; }
    int hash();
    static bool operator ==(Valued a, Valued b) => a.cents == b.cents;
}
```

<!-- example operator-choice -->
```csharp
// app/Parcel.sharp
namespace App;

public class Parcel : Valued, Weighed
{
    public int cents { get; }
    public int grams { get; }
    public Parcel(int cents, int grams) { this.cents = cents; this.grams = grams; }
    public int hash() => this.grams;
}
```

<!-- example operator-choice: statements -->
```csharp
Parcel light = new Parcel(500, 100);
Parcel heavy = new Parcel(500, 900);
light == heavy;                         // compile error: Valued and Weighed each declare an == that takes two Parcels
Valued lightPrice = light;
lightPrice == heavy;                    // true: only Valued's == takes a Valued
Weighed lightWeight = light;
lightWeight == heavy;                   // false: only Weighed's == takes a Weighed
```

Declaring `public static bool operator ==(Parcel a, Parcel b)` in `Parcel` gives two `Parcel`s their own `==`, which is more specific than either interface's.

**`==` and `!=` on a nullable type are lifted.** Null equals only null. Two non-null values use the type's own `==`, including a declared `operator ==`, so an `operator ==` takes two non-null values and never sees a null. `x != null` is the normal null check, and it never runs user code. `x is null` and `x is not null` stay valid as ordinary patterns (section 21).

<!-- example lifted-equality: statements
Money total = new Money(500, "USD");
-->
```csharp
Money? price = null;
price == null;                   // true, without running Money's ==
price == Money.zero("USD");      // false, without running Money's ==
total == Money.zero("USD");      // false: total is a Money, so this runs Money's ==
if (price != null) { … }         // the normal null check
if (price is not null) { … }     // compiles: an ordinary pattern
```

**Bitwise operators** `|`, `&`, `^`, `~`, `<<` and `>>`, and their compound forms such as `|=`, take `int` only. Flags are ints joined with `|`:

<!-- example bitwise: statements
bool isAdmin = true;
bool isOwner = false;
int count = -1;
-->
```csharp
const READ = 1;
const WRITE = 2;
const DELETE = 4;
let permissions = READ | WRITE;               // compiles
permissions |= DELETE;                        // compiles
if (permissions & WRITE != 0) { … }           // compiles: means (permissions & WRITE) != 0
if ((permissions & WRITE) != 0) { … }         // compiles: the same meaning
if (permissions & WRITE) { … }                // compile error: int is not bool
const shown = isAdmin | isOwner;              // compile error: | takes int; write ||
const mask = 1 << count;                      // throws ArithmeticError when count is negative
```

- A condition is a `bool` (section 21), so `if (permissions & WRITE)` is a compile error.
- `|` between two `bool`s is a compile error that names `||`.
- A shift by a negative count throws `ArithmeticError`.
- The bitwise operators bind tighter than comparisons. PHP binds them looser, so there `permissions & WRITE != 0` reads as `permissions & (WRITE != 0)`.
- In a type, `|` and `&` keep their meaning as unions and bounds (sections 11 and 24).

**Precedence**, from the tightest to the loosest. Operators in one row bind equally.

| Operators | Grouping | Compared with PHP |
|---|---|---|
| `.`, `?.`, calls, indexing `[]`, `new`, `match` | left | PHP writes `->`, `?->` and `::` |
| `**` | right | same |
| unary `-`, `~`, `++`, `--`, casts `(int)`, `(float)`, `(string)` | right | same, with fewer casts (section 24) |
| `with` | left | PHP# only |
| `!` | right | same |
| `*`, `/`, `%` | left | same |
| `+`, `-` | left | `+` also joins strings. PHP's `.` binds looser, below `<<` and `>>` |
| `<<`, `>>` | left | same |
| `&` | left | **differs:** PHP binds `&` looser than every comparison |
| `^` | left | **differs:** as `&` |
| `\|` | left | **differs:** as `&` |
| `<`, `<=`, `>`, `>=`, `is`, `as` | none | **differs:** `is` binds looser than PHP's `instanceof`, with the comparisons. `as` is PHP# only |
| `==`, `!=`, `===`, `<=>` | none | same |
| `&&` | left | same |
| `\|\|` | left | same |
| `??` | right | same |
| `? :` | right | **differs:** PHP refuses nesting without parentheses (section 21) |
| `=`, `+=`, `-=`, `*=`, `/=`, `%=`, `**=`, `??=`, `&=`, `\|=`, `^=`, `<<=`, `>>=` | right | same, without `.=` |
| lambda `=>`, `throw` | right | the lambda is PHP#'s. `throw` is as in PHP 8 |

PHP's `and`, `xor` and `or` do not exist (section 21).

**Interface names** have no `I` prefix and no `Interface` suffix: `Comparable`, `Linkable`. `IComparable` and `ComparableInterface` are naming warnings, as a name out of its case is (section 24).

## 20. Enums

**An enum has no effects,** as a struct has none (section 10): its methods, `get` bodies and operators, a data case's included, reach no database, network, file, clock or event, so an `emit` in one is a compile error that names the member and the effect: "`Status.announce` has the effect `Events`, and an enum has no effects."

An enum can give each case a raw value:

<!-- example backed-enum -->
```csharp
public enum Retry : string
{
    case None = "none";
    case ExponentialDays = "days";
}
```

The header lists the backing type and the interfaces after `:`, as a class header does (section 22). A leading `int` or `string` is the backing type, and every name after it is an interface:

<!-- example enum-interfaces -->
```csharp
public enum Status : string, HasLabel
{
    case Active = "a";
}
```

An enum case can also carry data. This is a closed set, and every case is declared in one block:

<!-- example enum-data-cases
public class Example
{
    public PaymentResult decline()
    {
        …
    }
}
-->
```csharp
public enum PaymentResult
{
    case Paid(string transactionId);
    case Declined(string reason);
    case RequiresAction(string redirect);
}

return PaymentResult.Declined(reason: "card expired");
```

An enum has exactly one of two shapes:

- **Backed:** every case has a value, as in `case None = "none"`, and no case carries data.
- **Not backed:** any mix of plain cases and cases that carry data.

<!-- example enum-shapes -->
```csharp
public enum CheckoutState
{
    case Open;                                   // plain
    case Paying(string gateway);                 // carries data
    case Done(Order order);
}
```

A case with a value and a case with data in one enum is a compile error.

A machine (section 20.2) is the one enum whose cases may carry data and a value at once.

- **Each case is its own type,** so a method can take `PaymentResult.Paid` alone.
- **Two cases are `==`** when they are the same case and each field is `==`, as two structs are (section 19). A case with a field that has no `==` gives its enum no `==`. An enum that declares its own `==` compares its cases by it instead, as a struct does.
- **A not-backed enum may declare type parameters,** with bounds and without `in` or `out`, as `enum Lookup<T> { case Found(T value); case Missing; }`. A backed enum declares none.
- **A case of a generic enum** is the type `Lookup<int>.Found`. A case takes its type arguments as a static method call does (section 11), so `Lookup<int> lookup = Lookup.Missing;` and `Lookup<int>.Missing` both compile. A pattern names the bare case, as in `lookup is Lookup.Found(int value)`, and takes the type arguments of the value it tests.
- **A plain case of a generic enum is one object for each list of type arguments,** so `Lookup<int>.Missing` and `Lookup<string>.Missing` are two objects, as each list has its own static fields.
- **At runtime,** each case is a real class, so Reflection sees it.
- **The checker** rejects a `match` over the enum that misses a case.

**A field takes any type,** a collection too. A list literal passed for a `Set` field becomes a `Set`, so a case compares its sets by their elements:

<!-- example case-set-field: statements -->
```csharp
public enum Badge
{
    case Marked(Set<string> tags);
    case Blank;
}

Badge badge = Badge.Marked(["vip", "new"]);                                           // compiles: the literal becomes a Set
bool same = badge == Badge.Marked(["new", "vip"]);                                    // true: the same case with the same elements is ==
int count = match (badge) { Badge.Marked(Set<string> tags) => tags.count(), Badge.Blank => 0 };   // compiles
```

**A field is a member of its case, and a case has every member of its enum,** so a field named like a property or a method of its enum is a compile error that names both. One name means one member (section 4):

<!-- example case-field-names -->
```csharp
public enum Amount
{
    case None;
    case Some(int cents);                        // compile error: the field cents shares its name with the property Amount.cents

    public int cents => 0;
}
```

**Every case is a value of each interface its enum names,** so each case gives each property the interface states, by a field of that name or by a property of its enum, and the enum gives each method. A case that gives a property no value is a compile error that names the case and the interface's property:

<!-- example case-interface-members -->
```csharp
// app/Labelled.sharp
namespace App;

public interface Labelled
{
    string label { get; }
}
```

<!-- example case-interface-members -->
```csharp
// app/Note.sharp
namespace App;

public enum Note : Labelled
{
    case Untitled;                               // compile error: the case Note.Untitled does not give label, which Labelled states
    case Titled(string label);                   // compiles: its field gives label
}
```

### 20.1 Sealed interfaces

A `sealed interface` is a closed set of classes. Its implementers must sit in the same namespace, and so in the same folder. The checker gathers the set from that namespace, so there is no list to maintain.

<!-- example sealed-interfaces -->
```csharp
// app/Interaction.sharp
namespace App;

public sealed interface Interaction { … }
```

<!-- example sealed-interfaces -->
```csharp
// app/PopupInteraction.sharp
namespace App;

public class PopupInteraction : Interaction { … }      // same namespace: allowed
```

- **Outside the namespace,** implementing it is a compile error.
- **A `match` over it** lists its implementers and takes no `default` (section 21).
- **Adding an implementer** makes every `match` that does not handle it a compile error.

Use an enum when the cases are small data. Use a sealed interface when each case is a full class with its own methods.

### 20.2 Machines

**A `machine` is a value that moves between cases, and it changes by its moves alone.** It declares its cases as an enum does, each with an optional value, and a case may hold a block of members. A move is a method that gives a case of the same machine: one in a case's block moves from that case, and one in the machine's body moves from each case that isn't `final`. An entry point is a static method of the machine that gives a case. Every other method is an ordinary member.

<!-- example machine -->
```csharp
// app/Shop/OrderStatus.sharp
namespace App.Shop;

public machine OrderStatus : string
{
    case Draft = "draft"
    {
        public OrderStatus.Paid pay(int cents)
        {
            if (cents <= 0) {
                throw new ValueError("A payment is positive.");
            }
            return OrderStatus.Paid(cents);
        }

        public OrderStatus.Cancelled cancel() => OrderStatus.Cancelled;
    }

    case Paid(int cents) = "paid"
    {
        public OrderStatus.Shipped ship() => OrderStatus.Shipped;
    }

    final case Shipped = "shipped";
    final case Cancelled = "cancelled";

    public static OrderStatus.Draft open() => OrderStatus.Draft;

    law paidIsPositive(OrderStatus.Paid paid) => paid.cents > 0;
}
```

<!-- example machine -->
```csharp
// app/Shop/Order.sharp
namespace App.Shop;

public class Order
{
    public Order(public int id { get; }) { }

    public OrderStatus status { get; private set; } = OrderStatus.open();
    public OrderStatus copied { get; set; } = OrderStatus.open();          // compile error: a machine property has a private set

    public bool pay(int cents)
    {
        if (this.status is OrderStatus.Draft draft) {
            this.status = draft.pay(cents);
            return true;
        }

        return false;
    }

    public void rush(int cents) { this.status = this.status.pay(cents); }   // compile error: a move is called on the case it moves from
    public void copy(Order o) { this.status = o.status; }                   // compile error: it changes only by a move of its own
    public void force() { this.status = OrderStatus.Paid(5); }              // compile error: only entry points and moves build a case
}
```

<!-- example machine -->
```csharp
// app/Shop/Invoice.sharp
namespace App.Shop;

public class Invoice
{
    public OrderStatus status { get; private set; } = OrderStatus.open();

    public bool pay(int cents)
    {
        if (this.status is not OrderStatus.Draft draft) {
            return false;
        }

        this.status = draft.pay(cents);
        return true;
    }
}
```

<!-- example machine -->
```csharp
// app/Shop/Run.sharp
namespace App.Shop;

public class Run
{
    public late OrderStatus status { get; private set; }

    public void open() { this.status = OrderStatus.open(); }
}
```

<!-- example machine -->
```csharp
// app/Shop/Line.sharp
namespace App.Shop;

public struct Line
{
    public OrderStatus status { get; init; } = OrderStatus.open();

    public static Line paid(Line line) => line.status is OrderStatus.Draft draft ? line with { status: draft.pay(5) } : line;   // compiles
    public static Line reset(Line line) => line with { status: OrderStatus.open() };                                              // compile error: it changes only by a move of its own
}
```

- **Only entry points, moves, `parse` and `Json.decode` build a case.** A case with no fields is one object.
- **A move belongs to the case it moves from.** A move in a case's block is a member of that case's type alone, and a move in the machine's body is a member of every case that isn't `final`. A call of a move through the machine's type, or through a union that holds a case without the move, is a compile error that names the test to write first: "`pay` moves from `OrderStatus.Draft`, and `this.status` may be `Paid`, `Shipped` or `Cancelled`: test it first, as in `if (this.status is OrderStatus.Draft draft)`." So no program calls a move from a case that lacks it.
- **A property of a machine type has a `private set`,** starts at an entry point, and changes only by a move of the value it holds: `this.status = draft.pay(cents)`, where a type pattern bound `draft` from `this.status`, in an `is`, in an `is not` that leaves, or in a `match` arm. A promoted constructor parameter of a machine type is a compile error, since a caller could pass any case.
- **A `late` property of a machine type** takes no initial value. Its write of an entry point is accepted in any method of its class, and every other write is a move. A read before the first write throws, as for every `late` property.
- **A struct's property of a machine type is `{ get; init; }`,** starts at an entry point by its initial value or its constructor, and a `with` that sets it takes a move of the original's value: `line with { status: draft.pay(5) }`, where `draft` was bound from `line.status`.
- **The checker checks the graph.** Every case is reachable from an entry point. A case with no move out is `final`, and a `final` case has no move out. When a machine has `final` cases, every case reaches one. Each is a compile error that names the case: "The case `OrderStatus.Paid` has no move out, so it is `final`."
- **A law whose one parameter is a case of the machine is an invariant** (section 28): the checker proves it after every entry point and every move.
- **A backed machine whose cases hold no data, and that is not marked `[Tag]`, stores as its case's value,** as a backed enum does (section 10), so `Json.encode(account);   // {"id":7,"balance":"12.50","status":"active"}` writes the `status` machine as `"active"`, and `parse`, `Json.decode` and `Query.decode` read the case back from its value. A machine with a case that holds data stores as a family, marked `[Tag]`.
- **A machine marked `[Tag]` stores as a family does** (section 10): `Json.encode` writes a case under its value under the tag's key and its fields, and `parse` and `Json.decode` read it back, refusing a name the machine has no case of and a field of the wrong type. A backed machine's values name its cases, so `[Key]` on a case of one is a compile error that names the value: "`OrderStatus.Paid` is named `paid` by its value, so it takes no `[Key]`." A machine with no backing names each case with `[Key]`, as a family names each variant. Each invariant of the case it reads runs as code, and one that fails throws a `ValueError` that names it: "OrderStatus.Paid breaks paidIsPositive."
- **A `match` over a machine lists every case and takes no `default`** (section 21).
- **Independent states are two machines on the holder,** and nested states are one machine of the flattened cases.

## 21. Pattern matching

`match` is the only branching construct on a value. `switch` is removed. `match` works both as an expression and, with block arms, as a statement. It compares strictly and never falls through.

<!-- example match-forms: statements
import Illuminate.Support.Facades.Log;
PaymentResult result = PaymentResult.Declined(reason: "card expired");
Order order = new Order();
private void deliver(Order order) { }
-->
```csharp
const message = match (result) {
    PaymentResult.Paid(string transactionId) when order.isTest => `Test payment ${transactionId}`,
    PaymentResult.Paid(string transactionId) => `Paid ${transactionId}`,
    PaymentResult.Declined d => `Declined: ${d.reason}`,
    PaymentResult.RequiresAction(string redirect) => `Continue at ${redirect}`,
};

match (result) {
    PaymentResult.Paid(string transactionId) => {
        this.deliver(order);
        Log.info(transactionId);
    },
    PaymentResult.Declined or PaymentResult.RequiresAction => {},
}
```

**Arms** are written `pattern => value,`, and a block arm is written `pattern => { … },`. Arms are tried from top to bottom. A `match` used as a statement takes block arms and expression arms.

**Covering every case:**

- **On an enum,** arms that handle every case need no `default`. A missed case is a compile error that names it.
- **On a sealed interface,** the same holds for its implementers (section 20.1).
- **On a union of classes,** such as `Twig|Leaf`, the same holds for its members: arms that name each member, or a class or interface each member extends or implements, need no `default`. A missed member is a compile error that names it. A `match` on a union of classes may still have a `default`.
- **A machine's cases** (section 20.2), as an enum's are: adding a case makes every `match` that misses it a compile error.
- **On a List,** arms that handle every list need no `default`. A list pattern without a rest handles lists of its number of items, and one with a rest every longer list too. Each element is covered by the same rules as a `match` on one value of the element type, so `[Circle c, ...]` and `[Square s, ...]` together handle every list of a sealed `Shape` with a first item. A typed rest must take any `List` of the element type, as `...List<Shape> rest` does. A missed list is a compile error that names it in plain words.
- **On a nullable enum, sealed interface, union of classes or List,** such as a `Status?`, a `(Twig|Leaf)?` or a `List<Money>?`, the arms handle `null` too.
- **On any other value,** `match` must have a `default` arm.
- **An arm with `when`** does not count toward covering a case.
- **A `match` on a project's enum or sealed interface lists its cases,** nullable or not, so a case added later is a compile error at each `match` that misses it. Cases that share an arm are joined with `or`, as in `Status.Pending or Status.Failed => {}`, and `default` there is a compile error that names the cases it stands for: "A `match` on `TransactionStatus` lists its cases; missing: `TransactionStatus.Failed` and `TransactionStatus.Canceled`." A `match` on a plain PHP enum, or on an enum of an installed package, keeps `default`, because its library can add cases in an upgrade, as one on an `int` or a `string` does.

<!-- example listed-cases -->
```csharp
// app/TransactionStatus.sharp
namespace App;

public enum TransactionStatus
{
    case Paid;
    case Pending;
    case Failed;
    case Canceled;
}
```

<!-- example listed-cases -->
```csharp
// app/Ledger.sharp
namespace App;

public class Ledger
{
    public static string state(TransactionStatus status) => match (status) {
        TransactionStatus.Paid => "paid",
        TransactionStatus.Pending or TransactionStatus.Failed or TransactionStatus.Canceled => "open",
    };

    public static string label(TransactionStatus status) => match (status) {
        TransactionStatus.Paid or TransactionStatus.Pending => "open",
        default => "closed",              // compile error: A `match` on `TransactionStatus` lists its cases; missing: `TransactionStatus.Failed` and `TransactionStatus.Canceled`.
    };
}
```

<!-- example match-coverage: statements
Status status = Status.Active;
int n = 3;
-->
```csharp
string label = match (status) {
    Status.Active => "on",
    Status.Paused => "paused",         // compile error: This `match` misses `Status.Closed`.
};
string size = match (n) {
    < 10 => "small",                   // compile error: a match on an int needs a default arm
};
```

<!-- example match-list-coverage -->
```csharp
// app/Shared/Money.sharp
namespace App.Shared;

public class Money
{
    public Money(public int amount { get; }, public string currency { get; }) { }

    public static int total(List<Money> items) => match (items) {
        [] => 0,
        [Money first, ...List<Money> rest] => first.amount + Money.total(rest),
    };                                 // compiles: every length is handled

    public static string label(List<Money> items) => match (items) {
        [] => "empty",
        [Money only] => "one",         // compile error: This `match` misses lists of 2 or more items.
    };
}
```

<!-- example match-list-sealed -->
```csharp
// app/Drawing/Shape.sharp
namespace App.Drawing;

public sealed interface Shape { }
```

<!-- example match-list-sealed -->
```csharp
// app/Drawing/Circle.sharp
namespace App.Drawing;

public class Circle : Shape { }
```

<!-- example match-list-sealed -->
```csharp
// app/Drawing/Square.sharp
namespace App.Drawing;

public class Square : Shape { }
```

<!-- example match-list-sealed -->
```csharp
// app/Drawing/Drawing.sharp
namespace App.Drawing;

public class Drawing
{
    public static int corners(List<Shape> shapes) => match (shapes) {
        [] => 0,
        [Circle c, ...List<Shape> rest] => Drawing.corners(rest),
        [Square s, ...List<Shape> rest] => 4 + Drawing.corners(rest),
    };                                 // compiles: every first item is handled

    public static int circles(List<Shape> shapes) => match (shapes) {
        [] => 0,
        [Circle c, ...List<Shape> rest] => 1 + Drawing.circles(rest), // compile error: This `match` misses lists whose first item is a `Square`.
    };
}
```

**Patterns:**

| Pattern | Example |
|---|---|
| constant | `200 =>`, `Status.Paid =>` |
| comparison | `< 1000 =>`, `>= 1000 and < 10000 =>` |
| case unpacked by position | `PaymentResult.Paid(string transactionId) =>` |
| whole case, named | `PaymentResult.Paid p =>` |
| properties | `{ status: 200, body: string body } =>` |
| list | `[] =>`, `[Line only] =>`, `[Line first, ...List<Line> rest] =>`, `[...List<Line> before, Line last] =>`, `[Line first, ..., Line last] =>` |

- **A pattern takes types and constants only,** such as `Circle`, `200` or `Status.Paid`. A local's value is compared in `when`.
- **A list pattern has at most one rest,** anywhere among its elements. `...List<Line> rest` takes the elements the other patterns leave, and a bare `...` skips them. A second rest is a compile error.
- **A pattern creates a variable** only through a typed declaration, such as `string transactionId`.
- **`and`, `or` and `not`** combine patterns. They exist only inside patterns.
- **`when` adds a condition** to an arm. The condition is an ordinary expression.

<!-- example bare-name-patterns: statements
Any? value = 10;
-->
```csharp
const limit = 10;
const label = match (value) {          // value is Any?
    int n when n == limit => "at the limit",   // compares value with the local limit's value, 10, in when
    Circle => "a circle",                      // a bare name in a pattern is a type
    default => "other",
};
```

<!-- example pattern-locals: statements
Any? value = 10;
-->
```csharp
const limit = 10;
const label = match (value) {
    limit => "at the limit",                   // compile error: limit is a local; compare its value in when
    default => "other",
};
```

**A property pattern matches only the properties it lists.** It can nest, and it can test the type at the same time:

<!-- example property-patterns: statements
Response response = new Response();
Any? payment = new Paid();
-->
```csharp
if (response is { status: 200 }) { … }                          // ignores headers, body and the rest
if (response is { status: 200, body: { type: "json" } }) { … }  // nests into a property that is itself an object
if (payment is Paid { amount: > 1000 } big) { … }               // tests the type and one property, and binds big
```

**`is` and `as`:**

<!-- example is-and-as: statements alone 3-4
PaymentResult result = PaymentResult.Declined(reason: "card expired");
-->
```csharp
if (result is PaymentResult.Paid p) { … }
if (result is PaymentResult.Paid(string transactionId)) { … }
const paid = result as PaymentResult.Paid;                    // PaymentResult.Paid?, null if it is not one
const paid = result as PaymentResult.Paid ?? throw new NotPaid(result);
```

- **`is`** tests a value against any pattern, and creates the pattern's variables when it matches.
- **Narrowing:** after `is`, with a name or without one, a local variable or parameter counts as the tested type for the rest of the block, and after a branch that leaves when the test holds, as the types the test did not match. Assigning to it inside the block ends the narrowing. A local or parameter that a lambda assigns is never narrowed, because the lambda may run between the test and the use. A lambda keeps the narrowing of a local it reads only when no assignment can run after the lambda is created. A property never narrows, a stored one included, because each read may give another value. To use the tested value of a property, or of such a local, bind it to a name, as in `is int t`.

<!-- example narrowing: statements
Any? entity = new Page();
private void render(string key) { }
-->
```csharp
if (entity is HasDesign) {
    this.render(entity.designKey());  // entity counts as HasDesign here
}
```

<!-- example property-narrowing: statements
Tab order = new Tab();
private void save() { }
-->
```csharp
if (order.total is int) { let next = order.total + 1; }              // compile error: a property never narrows
if (order.total is int t) { this.save(); let next = t + 1; }         // compiles: t holds the value that was tested
```

- **A variable that `is` creates exists only where the test held:** inside the `if` for `is`, and after an `if` whose block always exits for `is not`. A block always exits when every path through it ends in `return`, `throw`, `break` or `continue`.

<!-- example pattern-scope: statements
Any? shape = new Circle();
float area = 0.0;
-->
```csharp
if (shape is Circle circle) {
    area = circle.radius;              // compiles: here shape is a Circle
}
circle.radius;                         // compile error: `circle` exists only where `shape is Circle circle` is true
```

<!-- example is-not-scope: members
private Map<string, int> prices = [:];
private Receipt charge(int orderId, int price) => new Receipt();
-->
```csharp
public Receipt checkout(Map<string, Any?> payload, string plan)
{
    if (payload["orderId"] is not int orderId) { throw new BadPayload("orderId"); }
    if (this.prices[plan] is not int price) { return Receipt.unknownPlan(plan); }
    return this.charge(orderId, price);                     // orderId and price are both known here
}
```

- **A negative test is written `is not`.** `is` binds with the comparisons (section 19), so `!entity is HasDesign` reads as `(!entity) is HasDesign`. That is a compile error, because `!` takes a `bool`.

<!-- example negative-tests: statements
Any? entity = new Page();
-->
```csharp
if (entity is not HasDesign) { … }       // compiles
if (!entity is HasDesign) { … }          // compile error: write entity is not HasDesign
```

- **In a pattern, `not` binds tighter than `or` and `and`,** in `is` and in `match` arms alike, so `not Paid or Refunded` reads `(not Paid) or Refunded`, and parentheses put `not` over the whole `or`.

<!-- example not-or-parentheses: statements
Payment result = new Paid();
string? code = "A-1";
-->
```csharp
if (result is not Paid or Refunded) { … }      // compiles: (not Paid) or Refunded
if (result is not (Paid or Refunded)) { … }    // compiles: neither Paid nor Refunded
if (code is not null and not "") { … }         // compiles: (not null) and (not "")
```

- **`as`** converts a value to a type, or gives null.
- **`as` to a collection type checks every element,** wherever the value came from, and gives null if any element is wrong. So `as List<string> ?? throw …` throws on a wrong element.
- **`x is int?` matches an `int` or null.**
- **A pattern that can never match is a compile error.**

<!-- example impossible-patterns: statements
Any? x = 1;
private Circle next() => new Circle();
-->
```csharp
if (x is int?) { … }                   // compiles: an int or null
Circle circle = this.next();
if (circle is Square) { … }            // compile error: This pattern never matches the value it tests.
```

**Every type answers `is`, `as` and `match` the same way, function types included.** `value is Function<int(int)>` tests the function's parameter and result types while the code runs, as `value is List<int>` tests the list's elements. A test whose type the value's source never declares is a compile error that says the type is unknown, such as a function-type test on a `Closure` that a plain PHP library returns with no parameter or result types:

<!-- example type-tests -->
```php
class Legacy
{
    public static function doubler(): \Closure { return fn($x) => $x * 2; }
}
```

<!-- example type-tests -->
```csharp
// app/Stubs/Legacy.sharp
namespace App.Stubs;

import App.Legacy;

extern Legacy uses none;
```

<!-- example type-tests: statements -->
```csharp
Any? doubled = (int x) => x * 2;
doubled is Function<int(int)>;               // true
doubled is Function<string(string)>;         // false: the parameter and result types differ
Legacy.doubler() is Function<int(int)>;      // compile error: the type of Legacy.doubler()'s function is unknown
```

**A plain PHP parameter whose native type is `callable` or `Closure` and states no signature takes any function value,** whatever its parameters and its result, so `Template.each(visit)` compiles for a `visit` of none, two or three parameters, a method such as `this.visit` and a lambda.

**A call of a function whose type is unknown is a compile error** that names the `extern` line that states its type. A function plain PHP gives has no PHP# type, so its effects are unknown even when its docblock states its parameters and its result (section 29): `Legacy.doubler()(2)` is refused until `extern Function<int(int) uses none> Legacy.doubler() uses none;` states what `Legacy.doubler` gives, and then it compiles, checked as a call of any function value is. A function a plain PHP property holds is a member of the class that declares the property, so its call has the effects of that class's `extern` line.

<!-- example unknown-function-calls -->
```php
class Legacy
{
    public static function doubler(): \Closure { return fn($x) => $x * 2; }
    public static function tripler(): \Closure { return fn($x) => $x * 3; }
}
```

<!-- example unknown-function-calls -->
```csharp
// app/Stubs/Legacy.sharp
namespace App.Stubs;

import App.Legacy;

extern Legacy uses none;
extern Function<int(int) uses none> Legacy.doubler() uses none;
```

<!-- example unknown-function-calls -->
```csharp
// app/Report.sharp
namespace App;

public class Report
{
    public int doubled() => Legacy.doubler()(2);   // compiles: the line states the function's type
    public int tripled() => Legacy.tripler()(2);   // compile error: the type of Legacy.tripler()'s function is unknown
}
```

**A plain PHP closure's type is its declared PHP signature,** as a plain PHP property's type is its declared one (section 25). A parameter it declares no type for takes anything, and a result it declares no type for is `Any?`, so `fn () => new Order()` is a `Function<Any?()>`, never a `Function<Order?()>`. Its type lists no effects, so it fits every `uses`, as the checker takes a plain PHP callable:

<!-- example plain-closure-type-tests -->
```php
class Orders
{
    public static function typed(): \Closure { return static fn (int $id): ?Order => new Order(); }
    public static function untyped(): \Closure { return static fn ($id) => new Order(); }
}
```

<!-- example plain-closure-type-tests -->
```csharp
// app/Stubs/Orders.sharp
namespace App.Stubs;

import App.Orders;

extern Orders uses none;
```

<!-- example plain-closure-type-tests: statements -->
```csharp
import Sharp.Data.Database;

Any? typed = Orders.typed();
Any? untyped = Orders.untyped();
typed is Function<Order?(int)>;                // true: the types it declares
typed is Function<Order?(int) uses Database>;  // true: it lists no effects
typed is Function<Order?(string)>;             // false: it takes an int
untyped is Function<Order?(int)>;              // false: its result is Any?
untyped is Function<Any?(string)>;             // true: a parameter with no type takes anything
```

**A tuple type tests the number of elements and each element's type,** as `List<int>` tests each element. A pattern that starts with `[` is a list pattern, which tests the same, so a tuple type is written in `as` and as a member of a union:

<!-- example tuple-tests: statements -->
```csharp
Any? pair = [1, "a"];
Any? triple = [1, "a", 2];
Any? swapped = ["a", 1];
pair as [int, string];                       // [1, "a"]
triple as [int, string];                     // null: three elements
swapped as [int, string];                    // null: the element types differ
pair is int|[int, string];                   // true
```

**An intersection type tests every type it joins** (section 24), and the name a pattern gives holds the value as the whole intersection:

<!-- example intersection-tests: statements
public class SharedOrder : Order, Shareable { public SharedOrder(int id = 1) : super(id) { } }
-->
```csharp
Any? shared = new SharedOrder();
Any? plain = new Order();
shared is Order & Shareable;                 // true
plain is Order & Shareable;                  // false: an Order that is not Shareable
shared is Order & Shareable both ? both.total : 0;   // 500
plain as Order & Shareable;                  // null
```

**`Class<T>` tests that a value is a class value of `T` or of a class that extends or implements `T`.** A string that holds a class's full name, as `"App.RushOrder"`, is that class's class value, so it passes too. A string that names no class never passes, not even `Class<Object>`:

<!-- example class-value-tests: statements -->
```csharp
Any? rush = typeof(RushOrder);
Any? customer = typeof(Customer);
Any? named = "App.RushOrder";
Any? text = "rush";
rush is Class<Order>;                        // true: RushOrder extends Order
customer is Class<Order>;                    // false
named is Class<Order>;                       // true: the string names RushOrder
text is Class<Order>;                        // false: no class is named rush
text is Class<Object>;                       // false
(rush as Class<Order>) == typeof(RushOrder); // true
```

**Boolean operators:** `&&`, `||` and `!` exist only in expressions. PHP's `and`, `or` and `xor` operators are removed, so `=` can no longer bind before `and`.

**Conditions are `bool`.** `if`, `while`, `do … while`, `for`, `? :`, `&&`, `||`, `!` and `match`'s `when` take a `bool`. Any other type is a compile error, so PHP's truthiness never applies:

<!-- example bool-conditions: statements
List<int> items = [1];
-->
```csharp
if (items.count()) { … }        // compile error: int is not bool
if (items.count() > 0) { … }
```

**The ternary** `c ? a : b` gives `a` when `c` is true, and `b` otherwise.

- PHP's two-operand `a ?: b` does not exist. `??` covers null (section 14.4).
- `a ? b : c ? d : e` means `a ? b : (c ? d : e)`.

## 22. Inheritance

The class header lists the base class and interfaces after `:`. The checker knows which name is the class. A class or an interface that inherits from itself, through its own header or a chain of headers, is a compile error that names the chain.

<!-- example class-header -->
```csharp
public class Page : DatabaseEntity, Linkable, Shareable { … }
```

<!-- example inheritance-cycle -->
```csharp
public class Folder : Shortcut { }   // compile error: Folder inherits from itself: Folder → Shortcut → Folder
public class Shortcut : Folder { }   // compile error: Shortcut inherits from itself: Shortcut → Folder → Shortcut
```

**Methods of a PHP# class are closed unless the class opens them** with `virtual`, or by declaring them `abstract`. A subclass replaces a method only with a required `override`:

<!-- example virtual-methods -->
```csharp
// app/DatabaseEntity.sharp
namespace App;

public abstract class DatabaseEntity
{
    public virtual string label() { … }      // subclasses may replace it
    public void save() { … }                 // nobody may replace it
}
```

<!-- example virtual-methods -->
```csharp
// app/Page.sharp
namespace App;

public class Page : DatabaseEntity
{
    public override string label() { … }
}
```

**Methods of a plain PHP class are open unless PHP marks them `final`.** PHP has no `virtual`, so PHP's own rule decides. Replacing one still needs `override`:

<!-- example plain-php-overrides -->
```php
abstract class Report
{
    abstract protected function render(): string;
    public function title(): string { return 'Report'; }
    final public function id(): string { … }
}
```

<!-- example plain-php-overrides: alone 4-5 -->
```csharp
public class SalesReport : Report
{
    protected override string render() { … }          // compiles
    public override string title() => "Sales";         // compiles: title is not final in PHP
    public string title() => "Sales";                  // compile error: replaces Report.title, write override
    public override string id() { … }                  // compile error: id is final in Report
}
```

These are compile errors, whether the parent is PHP# or plain PHP:

- a missing `override`
- `override` when the parent has no such method
- overriding a PHP# method that is not `virtual` or `abstract`, in PHP# or in a plain PHP class: the checker refuses every replacement written in the project, and a subclass a library generates while the app runs may replace the method, though its objects are built only through the PHP# constructor (section 9), so no test double that skips it exists
- overriding a plain PHP method marked `final`
- an override that renames a parameter of the method it overrides. The error names both names.

<!-- example override-parameter-names -->
```csharp
// app/Image.sharp
namespace App;

public class Image
{
    public virtual void resize(int width, int height) { … }
}
```

<!-- example override-parameter-names -->
```csharp
// app/Thumbnail.sharp
namespace App;

public class Thumbnail : Image
{
    public override void resize(int w, int h) { … }   // compile error: w renames width, h renames height
}
```

**Classes are open** unless marked `final`. `final override` stops an override chain at that class.

**`super`** calls the parent's version:

<!-- example super-calls
public class Page : DatabaseEntity
{
    …
}
-->
```csharp
public override string label() => super.label() + " (page)";
```

**An interface can give a method or a computed property a default body.** PHP# declares no traits:

<!-- example interface-defaults -->
```csharp
// app/HasDesign.sharp
namespace App;

public interface HasDesign
{
    List<string> claims { get; }                            // abstract: each class declares its storage
    string designColumn { get; }                            // abstract
    string designKey() => `design:${this.designColumn}`;    // compiles: a default body
    string designLabel => `${this.designColumn} design`;    // compiles: a computed property's default body
    string designId() => `design:${this.id}`;               // compile error: a default body sees only HasDesign's members, and id is not one
}
```

<!-- example interface-defaults -->
```csharp
// app/Page.sharp
namespace App;

public class Page : DatabaseEntity, HasDesign
{
    public List<string> claims { get; set; } = [];          // the storage lives in the class that owns it
    public string designColumn => "design";
}
```

<!-- example interface-defaults: statements
Page page = new Page();
-->
```csharp
page.designKey();                                           // "design:design", from the default body
page.designLabel;                                           // "design design", from the default body
```

- A default body sees only the interface's own members.
- Every field is declared in the class that owns it. An interface holds no fields.
- A computed property's default body runs on each read, so it takes no `static` and no `lazy`. An enum declares no stored property; a computed property is allowed, static or not, and an enum takes an interface's computed default as a class does. Every enum already has `name`, and a backed enum `value`, so no computed property takes either name.
- A PHP# interface with default bodies compiles to a PHP interface plus a PHP trait named `<Interface>\Defaults` that holds the default bodies. A plain PHP class that implements it must also `use` that trait to get the defaults. PHP# code never names the trait, so section 23's rule that a class cannot share its full name with a namespace does not apply to it.

**An interface property states `get` or `init`, never `set`,** since an interface's members are public and a `set` reaches its own namespace at most (section 6.1). `string name { get; }` lets each class give it any `set` of its own, and `string kind { get; init; }` states `init`. A class or a struct gives it with `init`, and an event gives it through its parameter, which `new` sets once. Code sets it only while its object is created, and reads it through the interface. Any other accessor list an interface property states gets what that list gets on a class property:

<!-- example interface-init -->
```csharp
// app/Tagged.sharp
namespace App;

public interface Tagged
{
    string kind { get; init; }                              // compiles
    string title { get; set; }                              // compile error: an interface property states get or init
    string note { get; set; init; }                         // compile error: a property is written by set or by init, not both
}
```

<!-- example interface-init -->
```csharp
// app/Badge.sharp
namespace App;

public class Badge : Tagged
{
    public string kind { get; init; } = "plain";            // compiles: Tagged states kind with init, and { get; set; } is a compile error
    public string title { get; set; } = "";
    public string note { get; set; } = "";
}
```

<!-- example interface-init: statements -->
```csharp
Tagged tagged = new Badge { kind: "sale" };
tagged.kind;                                                // "sale"
tagged.kind = "new";                                        // compile error: kind is set only while its object is created
```

**A class has one method per name, whatever it inherits** (section 7). When two places give a class a body for the same method, the class writes the method itself. The places are two interfaces' default bodies, a default body and a plain PHP trait, two header traits, or a default body and the base class. Until the class writes it, the class is a compile error that names both places. Inside its own methods, the class runs one interface's default body with `super<Interface>`:

<!-- example one-body-per-method -->
```csharp
// app/ShowsLabel.sharp
namespace App;

public interface ShowsLabel
{
    string label() => "label";
}
```

<!-- example one-body-per-method -->
```csharp
// app/ShowsTitle.sharp
namespace App;

public interface ShowsTitle
{
    string label() => "title";
}
```

<!-- example one-body-per-method -->
```csharp
// app/Ticket.sharp
namespace App;

public class Ticket : ShowsLabel, ShowsTitle { }        // compile error: Ticket gets label from both ShowsLabel and ShowsTitle, so Ticket writes label
```

<!-- example one-body-per-method -->
```csharp
// app/Sticker.sharp
namespace App;

public class Sticker : ShowsLabel, ShowsTitle
{
    public override string label() => super<ShowsLabel>.label() + "/" + super<ShowsTitle>.label();
}
```

<!-- example one-body-per-method -->
```csharp
// app/Stray.sharp
namespace App;

public class Stray : ShowsTitle
{
    public string name() => super<ShowsLabel>.label();   // compile error: Stray does not implement ShowsLabel
}
```
<!-- run one-body-per-method
new Sticker().label();   // "label/title"
-->

- `super<Interface>` names an interface the class implements, and is written only in the class's own methods.
- Replacing a default body takes `override`, as replacing a base class's method does. Implementing an interface method that has no default body takes none.
- A header trait replaces the base class's method, as PHP's `use` does, so the two count as one place.

A sub-interface replaces its parent's default body with `override`, and the most specific interface's body wins:

<!-- example sub-interface-defaults -->
```csharp
// app/Shows.sharp
namespace App;

public interface Shows
{
    string label() => "shows";
}
```

<!-- example sub-interface-defaults -->
```csharp
// app/ShowsMore.sharp
namespace App;

public interface ShowsMore : Shows
{
    override string label() => "more";             // compiles: replaces the default body of Shows
}
```

<!-- example sub-interface-defaults -->
```csharp
// app/ShowsLess.sharp
namespace App;

public interface ShowsLess : Shows
{
    string label() => "less";                     // compile error: replaces the default body of Shows, so it writes override
}
```

<!-- example sub-interface-defaults -->
```csharp
// app/Fancy.sharp
namespace App;

public class Fancy : ShowsMore { }                // gets label from ShowsMore
```
<!-- run sub-interface-defaults
new Fancy().label();   // "more"
-->

<!-- example php-trait-defaults -->
```php
use App\Design\HasDesign;

class LegacyPage implements HasDesign
{
    use HasDesign\Defaults;                                 // gets designKey() from HasDesign's default body

    public array $claims = [];
    public string $designColumn = "design";

    public string $designLabel {                            // runs designLabel's default body
        get => $this->{'designLabel@App\Design\HasDesign'}();
    }
}
```

**An interface also holds constants and operators,** and no storage:

<!-- example interface-constants-and-operators -->
```csharp
// app/Priced.sharp
namespace App;

public interface Priced
{
    const string CURRENCY = "USD";                                     // compiles: an interface constant, read as Priced.CURRENCY
    int cents { get; }                                                 // compiles: abstract, each class gives it
    string label => `${this.cents} ${Priced.CURRENCY}`;                // compiles: a default body built from the interface's own members
    static int operator <=>(Priced a, Priced b) => a.cents <=> b.cents;   // compiles: every Priced compares by cents, so a < b works
    int count { get; } = 0;                                            // compile error: an interface holds no storage, so it gives no initial value
    law cheapFirst(Priced a, Priced b) => a.cents <= b.cents || a > b;  // compile error: a law is proved against code, and any class may implement an interface later
}
```

Each class that implements `Priced`, without its two refused lines, gets its constant, its default body and its operator:

<!-- example interface-members-in-classes -->
```csharp
// app/Book.sharp
namespace App;

public class Book : Priced
{
    public int cents { get; }
    public Book(int cents) { this.cents = cents; }
}
```

<!-- example interface-members-in-classes -->
```csharp
// app/Rare.sharp
namespace App;

public class Rare : Priced
{
    public int cents { get; }
    public Rare(int cents) { this.cents = cents; }
    public override string label => "rare";                                  // replaces the default body
    public static int operator <=>(Rare a, Rare b) => b.cents <=> a.cents;   // two Rares compare by it, the dearest first
}
```

<!-- example interface-members-in-classes: statements -->
```csharp
Priced.CURRENCY;                       // "USD"
Book.CURRENCY;                         // "USD": an implementer reads it too
new Book(250).label;                   // "250 USD": the default body
new Rare(250).label;                   // "rare": Rare's own
Priced cheap = new Book(100);
Priced dear = new Book(900);
cheap < dear;                          // true: the interface's <=>
cheap <=> dear;                        // -1
new Rare(100) < new Rare(900);         // false: Rare's own <=> wins for two Rares
new Rare(100) < new Book(900);         // true: only the interface's <=> takes a Rare and a Book
List<Priced> mixed = [new Rare(900), new Rare(100)];
mixed.sortedBy(item => item).map(item => item.cents);   // [100, 900]: sortedBy orders as < on two Priced does
```

<!-- example interface-members-in-classes -->
```php
// app/LegacyBook.php
namespace App;

class LegacyBook implements Priced
{
    use Priced\Defaults;                                    // gets the default bodies, without which this class is a compile error

    public function __construct(public int $cents) {}

    public string $label {                                  // runs label's default body
        get => $this->{'label@App\Priced'}();
    }
}
```

<!-- example interface-members-in-classes: statements -->
```php
(new App\LegacyBook(250))->label;      // "250 USD"
App\Priced::CURRENCY;                  // "USD"
```

- An interface constant follows a class constant's rules, and is read through the interface or any class that implements it.
- An interface holds no storage, so a property it states gives no initial value, and a computed property's expression is its default body.
- An interface states no law: a law is proved against the code of a class or an enum, and any class may implement an interface later.
- An interface's operator applies to every implementer, a plain PHP class included, as section 19 chooses every operator: from the types of both sides, the most specific one runs.
- A plain PHP class that implements an interface with default bodies uses `<Interface>\Defaults`, or it is a compile error that names the trait.
- A plain PHP class that extends or implements a PHP# type satisfies it as PHP links it: each method it declares or inherits, from the project or a package, fits the PHP declaration of the member it implements or overrides, by PHP's rules and without docblocks, or `mago compile` refuses the class's file. The next rule names the one exception: a method with no return type under an interface's member. That declaration erases the PHP# types, so `Self` is `static`, a `List` is `array`, a `Map` or a `Set` parameter, and such a result a plain PHP caller gets as its array, is `\Sharp\Map|array` or `\Sharp\Set|array`, a `Bytes` parameter, and such a result a plain PHP caller gets as its string, is `\Sharp\Bytes|string`, and `Any`, `Any?` and a type parameter without a bound declare no type. `mago compile` checks a plain PHP file only there, where it meets a PHP# declaration, and every other error in the file stays `mago analyze`'s, as in a plain PHP project.
- Each PHP# call of an interface's member checks the result, so a plain PHP method that declares no return type implements the member, whether the class declares it or inherits it from a package, as Eloquent's `Model` declares `getMorphClass()`. The member keeps the type it states, and a result of another type throws a `TypeError` that names the member and the type it states as PHP# writes them. A `void` or `never` member has no result to check. A call of such a method on the class, or on `this` in a PHP# subclass, is a call of the member: it has the member's type and checks its result. The class takes one type for the method, so members of its interfaces that state different results are a compile error. The check reads each collection in the result, a tuple's included, but none of its elements, so a call costs the same however many the result holds, unless the type holds a `float`, an `Any` or an `Object`, which section 24 checks wherever a value enters from plain PHP. A method that declares a return type still fits the member's PHP declaration, and a plain PHP method that overrides a PHP# class's method still declares one, since a call of a class's method checks nothing.
- A check inside a generic method reads the method's type parameter as any type, and one inside a generic class's static method reads the class's so too. So a call that gives a generic method, or a generic class's static method, the type argument its result names checks the result against that type: `Repository.first<Order>(source)` checks for an `Order`, and the error names the call, `App.Repository.first()`. A call that gives such a method a type parameter of its own leaves the check to the call that gives that one.

<!-- example interface-member-untyped-implementer -->
```csharp
// app/Taggable.sharp
namespace App;

public interface Taggable
{
    string morphClass();
}
```

<!-- example interface-member-untyped-implementer -->
```php
// app/Photo.php
namespace App;

abstract class Record
{
    public function __construct(private $morph) {}

    public function morphClass()                       // declares no return type, as Eloquent's Model does
    {
        return $this->morph;
    }
}

class Photo extends Record implements Taggable {}      // compiles: each call of morphClass() checks its result
```

<!-- example interface-member-untyped-implementer -->
```csharp
// app/Stubs/Record.sharp
namespace App.Stubs;

import App.Photo;
import App.Record;

extern Photo uses none;
extern Record uses none;
```

<!-- example interface-member-untyped-implementer: statements -->
```csharp
Taggable photo = new Photo("photo");
photo.morphClass();                     // "photo"
new Photo("direct").morphClass();       // "direct": a call of Taggable.morphClass(), typed string
Taggable scan = new Photo(42);
scan.morphClass();                      // throws: "App.Taggable.morphClass(): Return value must be of type string, int returned"
```

**A plain PHP trait** is listed in the header with the base class and interfaces, and it is also a type. It works everywhere an interface does:

<!-- example plain-php-traits -->
```csharp
// app/Order.sharp
namespace App;

import Illuminate.Database.Eloquent.Model;
import Illuminate.Database.Eloquent.Factories.HasFactory;

public class Order : Model, HasFactory { … }
```

<!-- example plain-php-traits -->
```csharp
// app/Draft.sharp
namespace App;

import App.Legacy.Copies;

public class Draft : Copies { … }               // compiles: a trait may stand alone in the header, as an interface may
```

<!-- example plain-php-traits: statements
Any? entity = new LegacyEntity();
-->
```csharp
import Illuminate.Database.Eloquent.Factories.HasFactory;

if (entity is HasFactory f) { … }
public void seed(HasFactory owner) { … }
List<HasFactory> owners = [];
```

Reflection lists a class's traits, just as it lists the class's interfaces.

## 23. Namespaces and imports

Namespace parts are separated with `.`, and imports use `import`. Every part of a `namespace` line is in Pascal case, as every type but the built-in ones is (section 24), so `namespace App.store;` is a naming warning and `namespace App.Store;` is none.

<!-- example namespace-and-import -->
```csharp
namespace App.Tenant.Store;
import App.Shop.Records.Entities.DatabaseEntity;
```

**Full names appear only in `namespace` and `import` lines.** Code uses the short imported name, so `.` in code is always member access. The last part of an import is always a class, or the namespace of a plain PHP library's functions and constants. A full class name inside code is a compile error that names the import to add. A module header names a namespace, as the `namespace` line does, so `module : App.Shop` compiles (section 32).

**No two namespaces import each other,** directly or through other namespaces, a parent and its child among them. The namespace is the folder, so the files of one namespace import each other freely. Each `import` that closes a cycle is a compile error that names the namespaces, the other file's import, and the two ways out: move the code, or declare an interface in one namespace that the other implements. An import one way compiles, from a parent to its child or from a child to its parent. A library's imports and the standard library's take part in no cycle, as they never reach the project's code.

<!-- example namespace-cycle -->
```csharp
// app/Tenant/Store/CartService.sharp
namespace App.Tenant.Store;

import App.Tenant.Store.Entities.Cart;    // compile error: `App.Tenant.Store` and `App.Tenant.Store.Entities` import each other (Cart.sharp imports CartService)

public class CartService { … }
```

<!-- example namespace-cycle -->
```csharp
// app/Tenant/Store/Entities/Cart.sharp
namespace App.Tenant.Store.Entities;

import App.Tenant.Store.CartService;      // compile error: `App.Tenant.Store.Entities` and `App.Tenant.Store` import each other (CartService.sharp imports Cart)

public class Cart { … }
```

**A plain PHP library's function is called through its namespace, as a member.** After `import Vendor.Pdf;`, the call is `Pdf.render(html)`. Importing a single function, or calling a function by its bare name, is a compile error, so every call outside the current class reads `Name.member(…)`:

<!-- example library-functions -->
```php
namespace Vendor\Pdf;

function render(string $html): string { return $html; }
```

<!-- example library-functions -->
```csharp
// app/Stubs/Pdf.sharp
namespace App.Stubs;

import Vendor.Pdf;

extern Pdf.render uses Files;
```

<!-- example library-functions -->
```csharp
// app/Invoices/Printer.sharp
namespace App.Invoices;

import Vendor.Pdf;

public class Printer
{
    public string print(string html) => Pdf.render(html);     // compiles: the function is called through its namespace
    public string draft(string html) => render(html);         // compile error: no function render exists
}
```

<!-- example library-functions -->
```csharp
// app/Invoices/Draft.sharp
namespace App.Invoices;

import Vendor.Pdf.render;                                     // compile error: import the namespace Vendor.Pdf, and call Pdf.render

public class Draft { }
```

**A function or constant a library declares outside any namespace has the namespace an `extern namespace` line gives its folder.** `extern namespace Illuminate.Foundation for abort;` in a `.sharp` file of `extern` declarations gives the folder of Laravel's `abort()`, with every function and constant of PHP's root namespace in it, the namespace `Illuminate\Foundation`. After `import Illuminate.Foundation;`, the call is `Foundation.abort(404)`, the value is `Foundation.abort`, and `extern Foundation.now uses Clock;` declares the effect of the `now()` beside it (section 29). The call runs PHP's `\abort(404)`. The line writes its namespace in full, or through the file's import of it, as `Foundation` after `import Illuminate.Foundation;`:

<!-- example library-root-functions -->
```php
namespace Illuminate\Foundation {
    class Application { }
}

namespace {
    function abort(int $code, string $message = ''): never { throw new RuntimeException($message, $code); }
}
```

<!-- example library-root-functions -->
```csharp
// app/Stubs/Laravel.sharp
namespace App.Stubs;

import Illuminate.Foundation;

extern namespace Illuminate.Foundation for abort;
extern Foundation.abort uses none;
```

<!-- example library-root-functions -->
```csharp
// app/Http/CartsController.sharp
namespace App.Http;

import Illuminate.Foundation;

public class CartsController
{
    public void missing() => Foundation.abort(404, "Cart not found.");   // compiles: the line gives abort's folder the namespace Illuminate.Foundation
    public void bare() => abort(404);                                    // compile error: no function abort exists
}
```

- **The line names a class, function or constant a library declares outside any namespace,** or it is a compile error. One of PHP's built-ins is no library's.
- **A namespace a class already has is one only a folder that holds a class of it takes,** as `helpers.php` sits beside the classes of `Illuminate\Foundation`, and giving it to another folder is a compile error.
- **Without a line, a function of PHP's root namespace belongs to no namespace PHP# code imports,** whatever classes sit beside it, so `Foundation.abort(404)` is the error a missing function gets, as PHP's own built-ins are never named.
- **An import of that namespace is an import like any other,** so a module's `imports` list holds it to the list (section 32).
- **`global` is an ordinary name.** `global.levenshtein(a, b)` is a compile error that says `global` names no class, and no namespace the file imports, as `gadget.levenshtein(a, b)` is.

**`extern namespace WordPress for WP_Post;` gives a library whose classes have no namespace the PHP# namespace `WordPress`.** The line stands in a `.sharp` file of `extern` declarations, and names a class of the library, which must be a plain PHP class outside any namespace, or it is a compile error. Its folder's namespace-less classes and functions are then `import WordPress;` and `WordPress.add_action(…)`, `import WordPress.WP_Post;` and `WP_Post`, and run PHP's `\add_action` and `\WP_Post`. A root class of another folder of the library takes no namespace from it. `extern WordPress.get_post uses Database;` declares an effect as any `extern` does (section 29), after `import WordPress;`. Two lines that name one folder and one namespace given to two folders are compile errors:

<!-- example extern-namespace -->
```php
namespace {
    class WP_Post { public function __construct(public string $post_title = '') {} }

    function add_action(string $hook, Closure $callback): bool { return true; }
}
```

<!-- example extern-namespace -->
```csharp
// app/Stubs/WordPress.sharp
namespace App.Stubs;

import WordPress;
import WordPress.WP_Post;

extern namespace WordPress for WP_Post;
extern WordPress.add_action uses none;
extern WP_Post uses none;
```

<!-- example extern-namespace -->
```csharp
// app/Blog/Feed.sharp
namespace App.Blog;

import WordPress;
import WordPress.WP_Post;

public class Feed
{
    public bool hook() => WordPress.add_action("init", () => { });   // compiles: runs PHP's \add_action
    public string title(WP_Post post) => post.post_title;            // compiles: WP_Post is PHP's \WP_Post
    public bool bare() => add_action("init", () => { });              // compile error: no function add_action exists
}
```

**PHP# code calls a method a plain PHP class declares,** by its PHP declaration or by its PHPDoc's `@method` or `@mixin`, as the checker reads them. A call only the class's `__call` or `__callStatic` answers, instance or static, is the error any undeclared method gets: on a `Bag` that declares `__call` and `__callStatic` alone, `bag.anything(1)` and `Bag.anything(1)` are refused with "Method `Bag.anything` does not exist.", where a call a `@mixin` declares, as Eloquent's `users.whereIn("id", ids)`, compiles and is typed by the method it names.

**An `extern` line states a plain PHP method's or function's signature,** so PHP# code calls it by types PHP# checks. The line is written in PHP# method order: the return type, the class and its type parameters, as in `Builder<TModel>`, the method, and the parameters. A PHP# call is checked against the signature the line states, never against the library's docblock, and `uses` follows it as on any `extern` line (section 29):

<!-- example typed-extern -->
```php
namespace Vendor\Routing;

function route(string $name, array $parameters = []): string { return '/' . $name; }

/** @template TModel */
final class Builder
{
    /** @return static */
    public function where($column, $value = null): static { return $this; }
}
```

<!-- example typed-extern -->
```csharp
// app/Http/RouteName.sharp
namespace App.Http;

public enum RouteName : string
{
    case Checkout = "checkout";
}
```

<!-- example typed-extern -->
```csharp
// app/Stubs/Routing.sharp
namespace App.Stubs;

import App.Http.RouteName;
import Vendor.Routing;
import Vendor.Routing.Builder;

extern string Routing.route(RouteName name) uses none;
extern Builder<TModel> Builder<TModel>.where(keyof TModel column, Any? value) uses Database;
```

<!-- example typed-extern -->
```csharp
// app/Http/Links.sharp
namespace App.Http;

import App.Order;
import Vendor.Routing;
import Vendor.Routing.Builder;

public class Links
{
    public string checkout() => Routing.route(RouteName.Checkout);   // compiles: plain PHP receives "checkout"
    public string bare() => Routing.route("checkout");               // compile error: route takes a RouteName
    public Builder<Order> paid(Builder<Order> query) => query.where(nameof(Order.status), "paid");   // compiles
    public Builder<Order> bareColumn(Builder<Order> query) => query.where("status", "paid");         // compile error: a string isn't a keyof Order, even one that names a member
}
```

- **The signature fits the library's native types, never its docblock.** Each stated parameter crosses to the native one, the stated return type takes the native one, and each native parameter after the stated ones has a default. A misfit is a compile error at the `extern` line, as `extern int Routing.route(RouteName name) uses none;` is: `route` returns a `string`. A native parameter without a type takes any stated type, so a line corrects a docblock that says `array` where the method reads an object. A native `callable` or `Closure` result is a function whose types PHP never declares, so any stated function type takes it, as `extern Function<int(int) uses none> Legacy.doubler() uses none;` states one (section 21).
- **A backed case crosses as its value** where the native parameter declares only scalars, as `string $name` does, so plain PHP receives `"checkout"`. A `keyof T` crosses as the member's name (section 11.2).
- **A parameter takes a literal default, as a method's does,** and a call that leaves the parameter out passes the line's default to plain PHP, by the library's name, whatever default the library declares. A literal is a string shorter than 1000 bytes, an `int`, a `float`, `true`, `false` or `null`. A default that runs code, as `int at = Hooks.now()`, is a compile error, and so is one the parameter's type does not take, as `int priority = "10"`. A call names an argument by the line's name only when the library's parameter at its place has that name, since PHP reads a named argument by the library's name.
- **A `callable` parameter takes any function value** (section 7), and crosses to a native `callable`, `Closure` or untyped parameter as the closure the value is.
- **A generic class writes its type parameters as it declares them,** as `Builder<TModel>` for Eloquent's `@template TModel`, and other names are a compile error.

**PHP itself is reached only through the standard library** (section 8). A built-in the standard library has no member for is declared with `[Import]`: `import Sharp.Interop.Import;`, then the attribute on a static method without a body makes each call run the PHP function it names. The signature is written in PHP# types, so each argument and the result cross as any plain PHP value does and are checked there, a `Map` argument passing as a PHP array and an array result arriving as the declared `List` or `Map`. The result crosses as an `extern` result of a built-in does, so a function type, alone or nullable, takes the native closure the function gives as it is, since PHP never declares a closure's types: `set_error_handler`'s previous handler arrives as the closure plain PHP installed. The declaration states its effects with `uses`, as any body-less declaration does. A function PHP doesn't declare throws PHP's `Error` that names it at the first call. `[Import]` on an instance method or a method with a body is a compile error, and so is a second `[Import]` of one function in the project, since PHP ignores the case of a function's name:

<!-- example built-in-through-import -->
```csharp
// app/Support/Words.sharp
namespace App.Support;

import Sharp.Interop.Import;

public static class Words
{
    [Import("levenshtein")]
    public static int distance(string from, string to);

    [Import("array_unique")]
    public static List<string> unique(List<string> items);
}
```

<!-- example built-in-through-import -->
```csharp
import App.Support.Words;

public class Search
{
    public int closeness(string query, string title) => Words.distance(query, title);   // compiles: runs PHP's levenshtein
    public List<string> tags(List<string> all) => Words.unique(all);                     // compiles: the result is renumbered
    public int bare(string query, string title) => levenshtein(query, title);           // compile error: no function levenshtein exists
}
```
<!-- run built-in-through-import
Search search = new Search();
search.closeness("cart", "carts");   // 1
search.tags(["a", "b", "a"]);        // ["a", "b"]
-->

**`[Export("name")]` on a static method makes PHP see the global function `name`, which calls the method.** `import Sharp.Interop.Export;` names the attribute. The function passes its arguments, named ones included, to the method, whose PHP# types check each one, and returns the method's result. Every exported function is declared when Composer's autoloader loads PHP#'s package, before the app's code and before a library that requires PHP#, so a library that declares its own only when none exists, as WordPress's pluggable functions do, finds the project's first and skips its own. PHP# code still calls the method by its own name. `[Export]` on an instance method is a compile error, and so is a second export of one name in the project, which names both methods:

<!-- example exported-function -->
```csharp
// app/Auth.sharp
namespace App;

import Sharp.Interop.Export;

public class Auth
{
    [Export("auth_redirect")]
    public static string redirect(string to, int status = 302) => `${status} ${to}`;

    [Export("auth_check")]                                       // compile error: only a static method is exported
    public bool check() => true;
}
```

**A plain PHP constant is read through its namespace the same way.** `Pdf.DEFAULT_DPI` reads the library's after `import Vendor.Pdf;`, and a constant a library declares outside any namespace is read through the namespace an `extern namespace` line gives its folder, as a function is. A bare uppercase name is always the code's own constant, so a bare `DEFAULT_DPI` is a compile error. PHP's own constants are reached through the standard library, as `Int.MAX` and `Int.MIN` give the `int` bounds:

<!-- example library-constants -->
```php
namespace Vendor\Pdf;

const DEFAULT_DPI = 300;
```

<!-- example library-constants -->
```csharp
// app/Invoices/Layout.sharp
namespace App.Invoices;

import Vendor.Pdf;

public class Layout
{
    public int largest() => Int.MAX;                              // compiles: the standard library gives the int bounds
    public int dpi() => Pdf.DEFAULT_DPI;                          // compiles: the library's constant is read through its namespace
    public int bare() => DEFAULT_DPI;                             // compile error: no constant DEFAULT_DPI exists
}
```

**An import never carries `uses`,** because a library's effect lives in its one `extern` declaration (section 29).

**The standard library lives under one root, `Sharp`,** with one standard library namespace per domain:

- `Sharp.Text` holds the members of `string`, `Regex` and `Html`.
- `Sharp.Time` holds `Clock`, `Instant`, `Duration`, `Date`, `DateTime`, `TimeZone`, `Weekday` and `Month`, and the members of a plain PHP date in `DateTimeInterfaceMembers`.
- `Sharp.IO` holds `Files`, `Path`, `Console` and `ZipArchive`.
- `Sharp.System` holds `Process`, `ProcessResult`, `ChildProcess` and `Environment`.
- `Sharp.Net` holds `Http`, `HttpRequest`, `HttpResponse`, `HttpMethod`, `Url`, `Ip`, `IpAddress`, `IpFamily`, `Dns`, `Mail`, `MailMessage` and `MailAddress`.
- `Sharp.Collections` holds `SortedMap`, `SortedSet` and `PriorityQueue`.
- `Sharp.Data` holds `Database` and `Cache`.
- `Sharp.Encoding` holds `Json`, `Serialized`, `PhpLiteral`, `Base64`, `Hex` and `Compression`.
- `Sharp.Security` holds `Hash`, `Hmac`, `Secret` and `Hashed`.
- `Sharp.Interop` holds `PhpGlobals` and `Superglobal`.

**`Sharp` itself is imported by default,** together with the standard library's extensions on `string`, `int`, `float`, `List`, `Map` and `Set`. A bare `Int` is `Sharp.Int`, a bare `Math` is `Sharp.Math`, a bare `Decimal` is `Sharp.Decimal`, a bare `Key` is the standard attribute, and `name.trim()` needs no import. `Sharp` holds the number types and their enums, and `Bytes` (section 18): `Int`, `Float`, `Math`, `Decimal`, `BigInteger`, `RoundingMode`, `ByteOrder` and `Bytes`. A class the file declares or imports, or a class in the file's own namespace, shadows the default one, `Error` included (section 31):

<!-- example own-namespace-shadows -->
```csharp
// app/Shop/Math.sharp
namespace App.Shop;

public static class Math
{
    public static int square(int value) => value * value;
}
```

<!-- example own-namespace-shadows -->
```csharp
// app/Shop/Release.sharp
namespace App.Shop;

public class Release
{
    public int area(int side) => Math.square(side);                  // compiles: Math is App.Shop.Math, with no import
    public int larger(int a, int b) => Math.max(a, b);               // compile error: App.Shop.Math declares no max
}
```

**PHP's standard exception classes need no import either.** A bare `ValueError`, `OutOfRangeException` or any other exception class in PHP's root namespace names PHP's own. So do `Throwable`, which every exception is, `Attribute`, which makes a class an attribute, and `Countable` and `JsonSerializable`, which a plain PHP library asks a class to implement. A class of the same name that the file declares or imports, or one in the file's own namespace, shadows it by the same rule:

<!-- example standard-exceptions -->
```csharp
// app/Shop/Gauge.sharp
namespace App.Shop;

public class Gauge : Countable                                                                              // compiles: Countable is PHP's, with no import
{
    public int count() => 0;

    public int first(List<int> values) => values.count() > 0 ? values[0] : throw new ValueError("empty");   // compiles: ValueError is PHP's, with no import
}
```

**PHP's other classes are names only the standard library uses.** A class, interface or enum PHP or one of its extensions builds in, such as `PDO`, `DateTimeImmutable` or `Random.Randomizer`, is the name a misspelling is in a `.sharp` file outside the standard library: its import is an import of no class, and its bare name names no type. The standard library has a type for each job: `Database` for a database, the types of `Sharp.Time` for a date, `Random` for random numbers, and `Files` for a file. A plain PHP library that reaches one is called as any library:

<!-- example built-in-classes -->
```csharp
// app/Shop/Stamp.sharp
namespace App.Shop;

import DateTimeImmutable;                                     // compile error: no class DateTimeImmutable exists

public class Stamp
{
    public int drivers() => PDO.getAvailableDrivers().count();  // compile error: no type PDO exists
}
```

**PHP 8.4's DOM is the standard library's way to read and write markup, an HTML page or an XML document, so a `.sharp` file names its classes through their namespace.** The standard library names `Dom.HTMLDocument`, `Dom.XMLDocument`, `Dom.Element`, `Dom.Node`, `Dom.NodeList`, `Dom.HTMLCollection`, `Dom.Text`, `Dom.Attr` and the rest of PHP's `Dom` namespace that reads and writes markup, and a file imports each by its full name, as `import Dom.HTMLDocument;`. `createFromString` and `createFromFile` read a document, `saveHtml` and `saveXml` write one, and an element's `innerHTML` and `outerHTML` give its markup. They run as PHP's own classes. PHP's older `DOMDocument` is one of PHP's other classes, so its import is still an import of no class:

<!-- example html-dom -->
```csharp
// app/Pages/Summary.sharp
namespace App.Pages;

import Dom.HTMLDocument;
import Dom.XMLDocument;

public static class Summary
{
    public static string? first(string html) => HTMLDocument.createFromString(html).querySelector("p")?.textContent;   // compiles: Dom.HTMLDocument is the standard library's

    public static string? entry(string xml) => XMLDocument.createFromString(xml).querySelector("entry")?.outerHTML;     // compiles: Dom.XMLDocument is the standard library's
}
```

**A static class in a standard library namespace needs one import line,** and the import's last part is the class, as for any class:

<!-- example standard-library-import: statements
Map<string, Any> payload = ["id": 1];
string text = "PHP#";
-->
```csharp
import Sharp.Encoding.Json;

Json.encode(payload);     // compiles
Base64.encode(text);      // compile error: Base64 is not imported
```

**`import X.Y as Z;` renames an import in this file only.** It compiles to PHP's `use X\Y as Z;`. Here the rename keeps the standard `Key`, which `import Cache.Key;` would shadow:

<!-- example import-rename -->
```csharp
import Cache.Key as CacheKey;                                    // renamed in this file only

public struct Entry
{
    public Entry(
        [Key("cache_key")] public CacheKey key { get; },         // Key stays the standard attribute, CacheKey is the library class
    ) { }
}
```

- **An imported name is used once per file.** Two `import` lines with the same name are a compile error, and so is an import named like a class the file declares. Renaming one of them fixes it.
- **A rename changes the name, not what is imported.** Other files keep the original name, and an `extern` written with the new name declares the same function, so section 29's one-declaration rule still counts it once.

**A class cannot share its full name with a namespace.** `Store.sharp` beside a `Store/` folder is a compile error, because both would be `App.Tenant.Store`. `Store/Store.sharp` is allowed, because it is `App.Tenant.Store.Store`. The rule also covers plain PHP classes the checker sees.

## 24. Built-in types and `Any`

Built-in types are lowercase: `int`, `float`, `bool`, `string`, `void`, `null`. Every other type is capitalized: `List`, `Money`, `Any`.

**Names** take one casing per kind:

- **PascalCase:** namespaces, types, type parameters, events and enum cases.
- **camelCase:** methods, properties, fields, parameters and locals.
- **UPPER_SNAKE_CASE:** constants.

<!-- example naming-cases -->
```csharp
// app/Billing/Status.sharp
namespace App.Billing;                                         // namespace

public enum Status : string { case Paid = "paid"; }            // type and enum case
```

<!-- example naming-cases -->
```csharp
// app/Billing/Invoice.sharp
namespace App.Billing;

public class Invoice<TLine : Line>                             // type and type parameter
{
    const MAX_LINES = 100;                                     // constant
    List<TLine> pendingLines = [];                             // field
    public string dueDate { get; set; } = "";                   // property

    public Money totalIn(string currency)                      // method and parameter
    {
        const lineCount = this.pendingLines.count();           // local
        …
    }
}
```

<!-- example naming-cases -->
```csharp
// app/Billing/InvoicePaid.sharp
namespace App.Billing;

public event InvoicePaid(int invoiceId, Money amount);         // event
```

A name that breaks the rule is a warning, as in "Method name `TotalIn` should be in camel case."

**Names are case-sensitive,** so names that differ only in case are two names: `total()` and `Total()` are two methods, and `Order` and `order` are two classes. Code reaches a declaration only by the name it declares, plain PHP code that calls PHP# code included. PHP's own classes and functions keep PHP's rule. A call written in another case reaches nothing, and the error names the declaration, as PHP# code's compile error "`App\Billing\Invoice` has no member `TotalIn`; did you mean `totalIn`?" and plain PHP's `Error` "Call to undefined method App\Billing\Invoice::TotalIn(); did you mean totalIn()?" do. A type written in another case names nothing either, as the compile error "Cannot find type `INVOICE`; did you mean `Invoice`?" says, and plain PHP's `new`, `instanceof`, `class_exists`, reflection, `unserialize` and autoloader find no class by it. A method that differs from an inherited one only in case is a second method, and an override names its parent's method exactly:

<!-- example exact-names: statements
public int totalIn(string currency) => 1;
-->
```csharp
this.totalIn("EUR");        // compiles
this.TotalIn("EUR");        // compile error: no member TotalIn; did you mean totalIn?
```

**A plain PHP class below a PHP# class overrides one twin by its exact name, and can't declare the other.** It is written in PHP, where two method names that differ only in case are one name. So a plain subclass of a class with `total()` and `Total()` may declare `Total()`, which overrides `Total()` alone, and declaring both is PHP's "Cannot redeclare". A plain class that implements a PHP# interface and uses its `Defaults` trait takes both twins from the trait, as two methods.

**A type holds null only when it is written with `?`,** for parameters, return types, properties and locals alike: `Customer?` may hold null, and `Customer` never does. Section 14.4 lists the compile errors for a `?` or a null check that cannot matter.

A `null` default needs the `?` too, for every type, on a field, a property or a parameter:

<!-- example null-defaults: members -->
```csharp
private string name = null;              // compile error: write string? name = null
private string? nickname = null;         // compiles
public void tag(string label = null)     // compile error: write string? label = null
```

**A union type is written inline,** such as `int|string`, anywhere a type goes. It compiles to PHP's own union type.

<!-- example union-types: members -->
```csharp
public User find(int|string id)
{
    if (id is int) { return User.byNumber(id); }
    return User.bySlug(id);   // id is a string here
}
```

- `is` narrows a union (section 21). After a branch that tested one type and returned, the code below it sees the types left.
- `List`, `Map` and `Set` share a union as every other type does, and `is` tells them apart.
- Alternatives that belong to the domain stay enums (section 20) and sealed interfaces (section 20.1).

**A union that holds null is written `(int|string)?`,** with the same `?` as any other type. `int|string|null` is a compile error that names `(int|string)?`, and `int|null` one that names `int?`.

- `null`, `true` and `false` are values, not types. `null` alone as a type is a compile error that says to write a type with `?` after it, as in `int?`. `true` or `false` alone, PHP's types, fails as a typo does.
- A built-in's failure never reaches PHP# code as `false`. An `[Import]` method (section 23) whose result type holds `null` and no `bool` reads the `false` its built-in returns for a failure as `null`, without PHP's warning for that failure, so `[Import("file_get_contents")] public static string? read(string path) uses Files;` gives `null` for a missing file. A result type without `null` throws the `TypeError` that names the function, and one that holds a `bool` gives the `false`.

<!-- example nullable-unions: members -->
```csharp
public (int|string)? find((int|string)? id)   // compiles
public int|string|null find2()                 // compile error: write (int|string)?
```

**An intersection type joins classes and interfaces with `&`,** such as `Shippable & Billable`, anywhere a type goes: a property, a parameter, a return type, a local and a pattern (section 21). A value is of it only when it is of every type it joins. A call from plain PHP that passes a value of only some of the types throws `TypeError`.

- **`&` joins classes and interfaces only.** `int & string` is a compile error at each member that is neither.
- **`&` joins at most one class,** with any number of interfaces, because an object extends one class chain. `Parcel & Box`, two classes, is a compile error.
- **A type written twice in one `&` is a compile error,** as it is in a union.
- **`&` binds tighter than `|`,** so `Shippable & Billable | Box` is the union of the intersection and `Box`. Parentheses, as in `(Shippable & Billable) | Box`, change nothing.
- **An intersection that holds null is written `(Shippable & Billable)?`.** `Shippable & Billable?` is a compile error that names it.

<!-- example intersection-types -->
```csharp
// app/Shipping/Shippable.sharp
namespace App.Shipping;

public interface Shippable { int weight(); }
```

<!-- example intersection-types -->
```csharp
// app/Shipping/Billable.sharp
namespace App.Shipping;

public interface Billable { int amount(); }
```

<!-- example intersection-types -->
```csharp
// app/Shipping/Parcel.sharp
namespace App.Shipping;

public class Parcel : Shippable, Billable
{
    public int weight() => 2;
    public int amount() => 30;
}
```

<!-- example intersection-types -->
```csharp
// app/Shipping/Box.sharp
namespace App.Shipping;

public class Box : Shippable
{
    public int weight() => 5;
}
```

<!-- example intersection-types -->
```csharp
// app/Shipping/Desk.sharp
namespace App.Shipping;

public class Desk
{
    public (Shippable & Billable)? last { get; set; } = null;           // compiles: holds both or null
    public int cost(Shippable & Billable item) => item.weight() * item.amount();
    public Shippable & Billable keep(Shippable & Billable item) => item;
    public string sort(Shippable & Billable | Box item) => item is Box ? "box" : "both";       // compiles: & binds tighter than |
    public string grouped((Shippable & Billable) | Box item) => item is Box ? "box" : "both";  // compiles: the same type
    public int count(int & string amount) => 0;                         // compile error: & joins classes and interfaces only
    public int both(Parcel & Box item) => 0;                            // compile error: & joins at most one class
    public int twice(Shippable & Shippable item) => 0;                  // compile error: Shippable is written twice
    public void hold(Shippable & Billable? item) { }                    // compile error: write (Shippable & Billable)?
    public int box() => this.cost(new Box());                           // compile error: a Box is Shippable, not Billable
}
```

<!-- example intersection-types: statements -->
```php
(new App\Shipping\Desk())->cost(new App\Shipping\Parcel());   // 60
(new App\Shipping\Desk())->cost(new App\Shipping\Box());      // throws TypeError: cost takes a Shippable & Billable
(new App\Shipping\Desk())->sort(new App\Shipping\Box());      // "box"
```

**Integer overflow throws `ArithmeticError`** at the operation that overflows. PHP's silent change to `float` does not happen in PHP# code. A shift is not an overflow: `<<` drops the bits it shifts out, and a shift by a negative count throws `ArithmeticError` (section 19).

- `int ** int` is an `int`. A negative exponent throws `ArithmeticError`, because its result is no `int`. A `float` on either side gives a `float`: `2.0 ** -1` is `0.5`.

<!-- example int-power: statements
int side = 3;
int a = 2;
int b = 3;
-->
```csharp
let area = side ** 2;        // int
2 ** -1                      // compile error: `2 ** -1` is an `int` raised to a negative power, which throws: write `2.0 ** -1`
a ** b                       // int. Throws ArithmeticError at runtime when b < 0
2.0 ** -1                    // 0.5
```

**`/` on two integers truncates toward zero.** `/` with a `float` operand stays float division.

<!-- example integer-division: statements -->
```csharp
7 / 2          // 3
-7 / 2         // -3
7 / 2.0        // 3.5
```

- Division by zero throws `DivisionByZeroError`.
- `Int.MIN / -1` throws `ArithmeticError`, because the result overflows.
- `/=` follows the same rules.
- Like `+` (section 18), what `/` does is chosen from the operand types the checker sees.

**An `int` where a `float` is expected is a compile error in PHP# code,** wherever a value lands, such as an assignment, an argument, a return, a property write, a list or map element, or a generic `T` that is `float`, as in `Cell<float>`.

<!-- example int-to-float: statements alone 1-4,6
int count = 3;
int n = 2;
-->
```csharp
float total = 0;                     // compile error: `int` is not `float`: write 0.0
float total = 0.0;                   // compiles
float price = count;                 // compile error: `int` is not `float`: convert with (float) count
float price = (float) count;         // compiles: the conversion is written out
Cell<float> cell = Cell.empty();
cell.put(2);                         // compile error: `int` is not `float`: write 2.0
cell.put(2.0);                       // compiles
cell.put((float) n);                 // compiles
bool same = cell.get() == 2.0;       // true
```

- A literal `0` is an `int`, whatever type the place expects.
- Arithmetic that mixes an `int` and a `float`, such as `count * 1.5`, gives a `float` and compiles.
- An `int` from plain PHP becomes a `float` wherever PHP# expects a `float`, such as a parameter, a generic `T` that is `float`, the elements of a `List<float>` or a `Set<float>`, and the values of a `Map<K, float>`. PHP# code then sees what its type says, so `cell.get() == 2.0` is true.

<!-- example plain-php-float-arguments: statements
$price = new App\Price();
$cell = App\Cell::empty();
$report = new App\Report();
-->
```php
<?php declare(strict_types=1);

$price->set(5);              // PHP# set(float value): value is 5.0
$cell->put(2);               // PHP# Cell<float>: the cell holds 2.0
$report->total([1, 2, 3]);   // PHP# total(List<float> amounts): amounts is [1.0, 2.0, 3.0]
```

**Converting values:**

<!-- example conversions: statements
import Illuminate.Http.Request;
Request request = new Request();
float price = 9.99;
Any? user = new User();
-->
```csharp
string text = request.input("qty") as string ?? ""; // an Any? is checked as a string first
const qty = Int.parse(text);                         // int, throws on "abc" and on "12abc"
const maybe = Int.tryParse(text);                    // int?, null on "abc"
const cents = (int)(price * 100);                    // truncates toward zero
const admin = user as Admin;                         // Admin?, null if user is not one
const flag = request.input("flag") == "1";           // replaces (bool)
```

- `(int)` and `(float)` convert between `int` and `float`. `(string)` converts a number to a `string`.
- `(int)` truncates a `float` toward zero. A `float` too large for an `int`, or NaN, throws `ArithmeticError`.
- A string becomes a number only by parsing. `Int.parse(s)` and `Float.parse(s)` take a `string`, and throw on a string that holds anything but a number, such as `"abc"` or `"12abc"`. Spaces around the number count too: `Int.parse(" 12 ")` throws, and its error says to call `trim()` first. An `Any?` is checked with `as string` first.
- `Int.tryParse(s)` and `Float.tryParse(s)` take a `string` too, and give null where `parse` throws.
- `Int.parse(s, radix)` and `Int.tryParse(s, radix)` read a number in a base from 2 to 36, with the letters `a` to `z` in either case past 9: `Int.parse("ff", 16)` is 255. A base outside 2 to 36 throws `ValueError` from both.
- An `int` and a `float` have number methods, from the standard library's extensions: `abs()`, `clamp(min, max)` and `format()`, which groups the digits in thousands. An `int` has `toHex()`, and `floorDiv(divisor)` and `mod(divisor)`, which round down where `/` and `%` round toward zero, so `(-7).floorDiv(2)` is -4 and `(-7).mod(2)` is 1. A `float` has `round(decimals)`, `floor()`, `ceil()`, `isFinite()` and `isNaN()`, as in `price.round(2)` and `total.format()`. `abs()` of the smallest `int` throws `ArithmeticError`, and a `clamp` whose minimum is above its maximum throws `ValueError`.
- `+`, `-` and `*` throw `ArithmeticError` past `Int.MAX` and `Int.MIN`. An `int`'s `checkedAdd`, `checkedSubtract` and `checkedMultiply` give null there instead, and `saturatingAdd`, `saturatingSubtract` and `saturatingMultiply` give `Int.MAX` or `Int.MIN`: `Int.MAX.checkedAdd(1)` is null, and `Int.MAX.saturatingAdd(1)` is `Int.MAX`.
- `Math` has `sqrt`, `pow`, `log`, `log10`, `exp`, `sin`, `cos`, `tan`, `asin`, `acos`, `atan` and `atan2`, which take radians, and the constants `Math.PI` and `Math.E`. Outside its domain a function gives NaN, as `Math.asin(2.0)` does.
- A class or an interface narrows only with `as`, which gives null, or with `as … ?? throw` (section 21).
- PHP# has no user-defined conversion operators, which would convert a value with no call written. A type converts only through a property or method it declares, such as `username.value`.
- `(bool)`, `(array)` and `(object)` do not exist. Conditions are `bool` (section 21), so a comparison such as `request.input("flag") == "1"` replaces `(bool)`. `(array)` and `(object)` are parse errors, as any form outside PHP# is (section 1), and the code below replaces them.
- PHP's cast aliases `(integer)`, `(double)`, `(boolean)` and `(binary)` do not exist.
- An `int` writes its 8 bytes with `toBytes(order)`, in the `ByteOrder` `Big` by default or `Little`, and `Int.fromBytes(bytes, order)` reads them back. The bytes are `Bytes`, as `Ip.toBinary` gives, and a negative `int` writes its two's complement. `Int.fromBytes` of any length but 8 throws `ValueError`.
- `Base64.encode(data, urlSafe: true)` writes the URL-safe alphabet of RFC 4648, with `-` and `_` for `+` and `/`, and no padding. `Base64.decode(text, urlSafe: true)` reads it with or without padding, and throws `ValueError` on a `+` or a `/`.

<!-- example int-bytes: statements
import Sharp.Encoding.Base64;
import Sharp.Encoding.Hex;
-->
```csharp
Hex.encode(258.toBytes());                          // "0000000000000102"
Hex.encode(258.toBytes(ByteOrder.Little));          // "0201000000000000"
Int.fromBytes((-1).toBytes());                      // -1
Base64.encode(Hex.decode("fbff"), urlSafe: true);   // "-_8"
Base64.decode("-_8", urlSafe: true).toList();       // [251, 255]
Int.fromBytes("short".toBytes());                   // throws ValueError
```

**`Decimal` is an exact decimal number, and `BigInteger` an integer of any size.** Both are structs in `Sharp`, so they need no import, and run on PHP's bcmath, which every engine builds:

- `Decimal.parse(text)` and `Decimal.tryParse(text)` read digits with an optional sign and point, as `"-12.50"`, and `Decimal.of(n)` takes an `int`. The digits after the point are its `scale`, so `"1.50"` has a scale of 2 and writes itself back as `"1.50"`.
- `+`, `-` and `*` give the exact result: a sum's scale is the larger of both, and a product's their sum. There is no `/`, because an exact quotient can have no end. `divide(divisor, scale, mode)` gives the quotient with `scale` digits after the point, and `round(scale, mode)` rounds the number to `scale` digits, both by a `RoundingMode`, `HalfEven` by default, as banks round. The modes are `HalfEven`, `HalfUp`, `HalfDown`, `Up` and `Down`, away from 0 and toward it, and `Ceiling` and `Floor`. A divisor of 0 throws `DivisionByZeroError`.
- `==` and `<=>` compare the number, so `1.5 == 1.50` is true, and each keeps its scale.
- `BigInteger.parse(text)`, `BigInteger.tryParse(text)` and `BigInteger.of(n)` make one, `+`, `-` and `*` never overflow, and `/` and `%` round toward 0, as an `int`'s do. `pow(n)` raises it, and a negative exponent throws `ArithmeticError`, as `**` on an `int` does. `toString(radix)` writes it in a base from 2 to 36, and `toIntOrNull()` gives it as an `int`, or null past `Int.MAX` and `Int.MIN`.

<!-- example exact-numbers: statements -->
```csharp
Decimal.parse("0.1") + Decimal.parse("0.2") == Decimal.parse("0.3");   // true
Decimal.parse("10").divide(Decimal.parse("3"), 2).toString();          // "3.33"
Decimal.parse("2.5").round(0).toString();                              // "2": HalfEven rounds to the even neighbor
Decimal.parse("2.5").round(0, RoundingMode.HalfUp).toString();         // "3"
(Decimal.parse("1.50") * Decimal.of(3)).toString();                    // "4.50"
const past = BigInteger.parse("9223372036854775807") + BigInteger.of(1);
past.toString();                                                       // "9223372036854775808"
past.toIntOrNull();                                                    // null
BigInteger.of(255).toString(16);                                       // "ff"
(BigInteger.of(-7) / BigInteger.of(2)).toString();                     // "-3"
Decimal.parse("1") / Decimal.parse("3");                               // compile error: Decimal has no /, so call divide with a scale
```

**Time is values,** never an `int` of seconds or a format of PHP's letters. `Sharp.Time` holds them:

- An `Instant` is a point on the time line, which `Clock.now()` gives (section 29).
- A `Duration` is a length of time in nanoseconds.
- A `Date` is a day of the calendar, with no time and no time zone.
- A `TimeOfDay` is a time on a clock, with no date and no time zone, which writes itself as `"21:30:00"`. A `DateTime`'s `timeOfDay` gives its own.
- A `TimeZone` is a zone of the IANA time zone database, such as `Europe/Sofia`, or a fixed offset from UTC, such as `+03:00`.
- A `DateTime` is an `Instant` as the clocks of a `TimeZone` show it.

Each one's `toString()` writes ISO-8601, and its `parse` reads it back and throws `ValueError` on any other text, while `tryParse` gives null instead. A `DateTime` writes its zone after the offset, as RFC 9557 does. A day or a zone that does not exist throws `ValueError`:

<!-- example time-values: statements
import Sharp.Time.Date;
import Sharp.Time.Duration;
import Sharp.Time.Instant;
import Sharp.Time.TimeZone;
-->
```csharp
const paid = Instant.parse("2026-10-22T18:30:00Z");
paid.plus(Duration.ofHours(2)).toString();                // "2026-10-22T20:30:00Z"
paid.inZone(new TimeZone("Europe/Sofia")).toString();     // "2026-10-22T21:30:00+03:00[Europe/Sofia]"
paid.inZone(TimeZone.utc).date.plusMonths(4).toString();  // "2027-02-22"
Date.parse("2026-01-31").plusMonths(1).toString();        // "2026-02-28"
Date.parse("2026-10-01").daysUntil(Date.parse("2026-10-22")); // 21
Duration.parse("PT1H30M").minutes;                        // 90
Instant.tryParse("yesterday");                            // null
new Date(2026, 2, 29);                                    // throws ValueError
```

Two `Date`s, `Instant`s, `Duration`s or `DateTime`s order with `<`, `<=`, `>` and `>=`. A `DateTime` orders by its instant and then by its zone's name, so its `<=>` is 0 exactly when `==` is true. A zone's name is spelled exactly as the time zone database spells it, so `EEST` and `europe/sofia` name no zone. A name of the database names a zone only when PHP runs it with the database's rules, so `CET`, `GMT` and `EST`, which PHP reads as a fixed abbreviation, name no zone, and `TimeZone.names()` leaves them out: name the region, as `Europe/Brussels`. A fixed offset from `-18:00` to `+18:00`, written as `+03:00`, names a zone. A test module binds `new Clock.fixed(instant)` in place of the real `Clock`, and its `now()` is always that instant. After `import Sharp.Time.DateTimeInterfaceMembers;`, a plain PHP date, such as a Carbon, becomes an `Instant` with `toInstant()` or a `DateTime` with `toDateTime()`. `toPhpDateTime()` turns an `Instant` or a `DateTime` back into a plain PHP date. None of these four reads the clock.

A zone's `fixedOffset` is the offset of a zone whose clocks never change, read from the zone's rules and never from its name: a fixed offset, as `+03:00`, `UTC` and `Etc/GMT-3` each have one. A zone the database gives any change of offset, as `Europe/Sofia`, has none. A type that takes only a zone whose clocks follow a region's daylight saving refuses any zone that never changes its clocks in its constructor:

<!-- example time-zone-kinds: statements
import Sharp.Time.Duration;
import Sharp.Time.TimeZone;
-->
```csharp
public struct HomeZone
{
    public HomeZone(public TimeZone zone { get; })
    {
        if (zone.fixedOffset is Duration) {
            throw new ValueError(`A home zone follows a region's daylight saving, and the clocks of "${zone.name}" never change.`);
        }
    }
}

new TimeZone("+03:00").fixedOffset?.minutes;            // 180
new TimeZone("Etc/GMT-3").fixedOffset?.minutes;         // 180
TimeZone.utc.fixedOffset?.minutes;                       // 0
new TimeZone("Europe/Sofia").fixedOffset;                // null
new HomeZone(new TimeZone("Europe/Sofia")).zone.name;   // "Europe/Sofia"
new HomeZone(TimeZone.utc);                              // throws ValueError
```

A format is named by the text it writes, never by PHP's format letters. A `DateTime`'s `toOffsetString()` writes ISO-8601 with the offset and no zone, its `toSqlString()` a SQL `DATETIME`, and an `Instant`'s `toHttpDate()` the HTTP date of RFC 9110. `DateTime.parseLocal(text, zone)` reads a date and a time of day on a zone's clocks, with a space or a `T` between them. A `Date` and a `DateTime` give their `weekday` and `monthOfYear` as the enums `Weekday` and `Month`, whose `toString()` is the English name. Any other text is a template of the fields:

<!-- example time-texts: statements
import Sharp.Time.DateTime;
import Sharp.Time.Instant;
import Sharp.Time.TimeZone;
import Sharp.Time.Weekday;
-->
```csharp
const sofia = Instant.parse("2026-10-22T18:30:00Z").inZone(new TimeZone("Europe/Sofia"));
sofia.toOffsetString();                                   // "2026-10-22T21:30:00+03:00"
sofia.toSqlString();                                      // "2026-10-22 21:30:00"
sofia.instant.toHttpDate();                               // "Thu, 22 Oct 2026 18:30:00 GMT"
sofia.weekday == Weekday.Thursday;                        // true
sofia.monthOfYear.toString();                             // "October"
DateTime.parseLocal("2026-10-22 21:30:00", TimeZone.utc).toString(); // "2026-10-22T21:30:00+00:00[UTC]"
```

**Replacing `(array)` and `(object)`:**

<!-- example array-object-replacements: statements
import Sharp.Encoding.Json;
Object row = Rows.withId(1);
string email = "ada@example.com";
-->
```csharp
OrderRow order = OrderRow.parse(row);             // replaces (array)row: a struct reads an object's public properties (section 10)
Map<string, Any> payload = ["id": 1, "email": email];
string json = Json.encode(payload);               // {"id":1,"email":"…"}: replaces json_encode((object)[…])
const data = (array)row;                          // parse error
const point = (object)["x": 1];                   // parse error
```

- **A JSON object** is a `Map` literal for a one-off payload, and a declared struct for a shape that repeats.
- **`Json.encode` encodes by the value's PHP# type,** so a `Map` is a JSON object and a `List` is a JSON array wherever it is, below an `Any` too, even when empty, and even when a `Map`'s keys run from 0 to n. A `Map` is an object the engine tells apart from a `List` (section 12).
- **A struct or a class is written by the fields its `parse` reads,** each under its key, a value as text such as `Decimal` as its `toString()`, `"1.50"`, and a family's variant with its name under the tag's key (section 10), so `Json.decode` reads back what `Json.encode` wrote.

<!-- example json-encoding: statements
import Sharp.Encoding.Json;
-->
```csharp
Map<string, int> none = [:];
Json.encode(none);                                // {}
List<int> empty = [];
Json.encode(empty);                               // []
Map<string, Any> loose = ["counts": none];
Json.encode(loose);                               // {"counts":{}}: an empty Map below Any is still an object
Map<string, Any> listed = ["counts": empty];
Json.encode(listed);                              // {"counts":[]}: an empty List below Any is still an array
```

**`Any` holds a value of any type except null. `Any?` also allows null.** A value of type `Any` must be checked with `is`, `as` or `match` before it can be used:

<!-- example any-checks: statements
import Sharp.Encoding.Json;
string body = "{}";
-->
```csharp
Any? payload = Json.parse(body).value;
payload.order;                              // compile error: check what payload is first
if (payload is WebhookPayload) {
    payload.order.total;                    // narrowed to WebhookPayload
}
```

**An unchecked `Any?` can be given a default or compared with a plain value:**

- `x ?? y` compiles and gives `Any`.
- `==` against a struct, enum, string, number, `bool` or collection compiles, and compares by value (section 19).
- `==` against an object, or against another unchecked `Any?`, is a compile error.
- `===` against an object compiles: it asks only whether both are the same object (section 19), so it is false when `x` holds no object.
- `${x}` in a template is a compile error until `x` is checked.

<!-- example unchecked-any: statements
Order order = new Order();
Any? other = 1;
-->
```csharp
Any? raw = Settings.raw("plan");
Any plan = raw ?? "free";          // compiles: ?? gives Any, a value that is never null
bool b = raw == "pro";             // compiles: compares with a string
bool c = raw == order;             // compile error: is raw the same object, or an equal value?
bool d = raw == other;             // compile error: other is an unchecked Any? too
string a = `plan: ${raw}`;         // compile error: check raw with is, as or match first
```

PHP's `mixed` is removed. Values coming from plain PHP that are typed `mixed` or untyped arrive as `Any?`. Decoding straight into a type, such as `Json.decode<WebhookPayload>(body)`, is the normal path: it reads any type a field `parse` reads can hold, as `List<Payment>`, the way a struct's `parse` reads that field (section 10), so a bad part throws the one error that lists every bad part, and a type `parse` can't read is a compile error. A number or a bool must be one in the JSON, so `"2"` is no `int`. `Query.decode<Search>("q=shoes&page=2")` reads a query string the same way, and since a query holds only text, it reads a number or a bool from its text, so `page` is `2` and `page=x` is the one problem "page: expected int, got string 'x'". `Json.parse(body)` gives any JSON value as a `JsonValue`, whose `value` is the `Any?` it holds, and two `JsonValue`s are `==` when their values are, at every depth:

<!-- example json-values: statements
import Sharp.Encoding.Json;
-->
```csharp
Json.parse("{\"a\":[1,2]}") == Json.parse("{ \"a\": [1, 2] }");   // true
Json.parse("{\"a\":[1,2]}") != Json.parse("{\"a\":[2,1]}");       // true
Json.parse("{\"a\":1,\"b\":2}") == Json.parse("{\"b\":2,\"a\":1}"); // true: an object's keys in any order
```

**`Json.encode` takes `escapeSlashes`, `escapeUnicode`, `keepZeroFraction`, `indent` and `escapeHtml`,** which default to `false`, `false`, `true`, `0` and `true`, its output without them. `escapeSlashes` writes `/` as `\/`, `escapeUnicode` writes each character outside ASCII as `\u` and its code, and `keepZeroFraction: false` writes `1.0` as `1`, so a payload can match the bytes another system signs. `escapeHtml` writes `<`, `>`, `&`, `'` and `"` inside a string as `\u003C`, `\u003E`, `\u0026`, `\u0027` and `\u0022`, so JSON put inside a `<script>` can't close it, and `escapeHtml: false` writes them as themselves, `"` as `\"`. `indent` writes each nested value on its own line, `indent` spaces deeper than its parent, with `": "` after a key and `[]` and `{}` for an empty value, so `indent: 4` writes the bytes of PHP's `JSON_PRETTY_PRINT`. A NaN or infinite `float` is written as `null`, since JSON has no number for it. A negative `indent` throws a `ValueError`:

<!-- example json-switches: statements
import Sharp.Encoding.Json;
Map<string, Any> payload = ["url": "https://example.com/a", "name": "école", "total": 1.0];
-->
```csharp
Json.encode(payload);                                                              // "{\"url\":\"https://example.com/a\",\"name\":\"école\",\"total\":1.0}"
Json.encode(payload, escapeSlashes: true, escapeUnicode: true, keepZeroFraction: false); // "{\"url\":\"https:\\/\\/example.com\\/a\",\"name\":\"\\u00e9cole\",\"total\":1}"
Json.encode(payload, keepZeroFraction: false);                                     // "{\"url\":\"https://example.com/a\",\"name\":\"école\",\"total\":1}"
Json.encode(["tags": ["a"], "none": [:]], indent: 2);                              // "{\n  \"tags\": [\n    \"a\"\n  ],\n  \"none\": {}\n}"
Json.encode(payload, indent: -1);                                                  // throws: "Json.encode: indent is -1, and it must be 0 or more"
Json.encode(["tag": "<b>"]);                                                       // "{\"tag\":\"\\u003Cb\\u003E\"}"
Json.encode(["tag": "<b>"], escapeHtml: false);                                    // "{\"tag\":\"<b>\"}"
Json.encode(Math.asin(2.0));                                                       // "null": NaN
Json.encode([1.5, Math.log(0.0)]);                                                 // "[1.5,null]": negative infinity
```

**`Secret<T>` and `Hashed<T>`, in `Sharp.Security`, keep a password, a token or a key out of plain sight.** A `Secret` holds its value in memory, and `value` gives it back. Its stored form, which `Json.encode` and `serialize` write and a field `parse` reads (section 10), is `{"encrypted": "…"}`: the value's own stored form encrypted with XChaCha20-Poly1305 under the newest key of the key ring `SHARP_KEYS`, bound to the type and field that hold it, as `App.SmtpAccount.password`, so it decrypts in no other field. `SHARP_KEYS` holds comma-separated `<id>:<base64 of 32 bytes>` keys, newest first, and `vendor/bin/mago generate-key` prints a new one. A new key put first encrypts each new `Secret`, and text under an older key still decrypts while the ring holds that key. A field reads an object whose one key is `encrypted` as the encrypted form and any other value as the plain value, read as `T`, so `T` is any type with a stored form. A missing `SHARP_KEYS` throws `RuntimeException` "a Secret is encrypted with the keys in SHARP_KEYS, which is not set. Generate a key with `vendor/bin/mago generate-key`.", and a `Secret` stored outside a field, as `Json.encode(new Secret<string>("x"))`, throws the `Error` a type with no stored form throws. A `Hashed` keeps only the Argon2id hash of its value's stored form, so it gives no value back, and `verify(value)` checks a value against it. Its stored form is `{"hashed": "<PHC string>"}`, and a field reads an object whose one key is `hashed` as the hash and any other value as the value to hash. Both show as `**********`, in a template and in a plain PHP dump such as `var_dump`:

<!-- example secret-values: statements
import Sharp.Encoding.Json;
import Sharp.Security.Secret;
import Sharp.System.Environment;
public Example(Environment environment) { environment.setVariable("SHARP_KEYS", "spec:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="); }
-->
```csharp
public class SmtpAccount
{
    public string host { get; }
    public Secret<string> password { get; }

    public SmtpAccount(string host, Secret<string> password)
    {
        this.host = host;
        this.password = password;
    }
}

const account = Json.decode<SmtpAccount>("{\"host\":\"smtp.example.com\",\"password\":\"hunter2\"}");
account.password.value;                                  // "hunter2"
`password: ${account.password}`;                         // "password: **********"
const stored = Json.encode(account);                     // {"host":"smtp.example.com","password":{"encrypted":"…"}}
Json.decode<SmtpAccount>(stored).password.value;         // "hunter2"
new SmtpAccount("smtp.example.com", new Secret<string>("hunter2")).password.value;   // "hunter2"
new SmtpAccount("smtp.example.com", "hunter2");          // compile error: password takes a Secret<string>, so write new Secret<string>("hunter2")
```

<!-- example hashed-values: statements
import Sharp.Encoding.Json;
import Sharp.Security.Hashed;
User a = new User("ada@example.com", new Hashed<string>("hunter2"));
User b = new User("ada@example.com", new Hashed<string>("hunter2"));
-->
```csharp
public class User
{
    public string email { get; }
    public Hashed<string> password { get; }

    public User(string email, Hashed<string> password)
    {
        this.email = email;
        this.password = password;
    }
}

const user = Json.decode<User>("{\"email\":\"ada@example.com\",\"password\":\"hunter2\"}");
user.password.verify("hunter2");          // true
user.password.value;                      // compile error: a Hashed<string> gives no value back; check one with verify(value)
a.password == b.password;                 // compile error: two Hashed<string> never compare, since each has its own salt; check one with verify(value)
`password: ${user.password}`;             // "password: **********"
const stored = Json.encode(user);         // {"email":"ada@example.com","password":{"hashed":"$argon2id$v=19$m=65536…"}}
Json.decode<User>(stored).password.verify("hunter2");   // true
new User("ada@example.com", new Hashed<string>("hunter2")).password.verify("hunter2");   // true
new Hashed<int>(4821).verify(4821);       // true
```

**A string-backed enum case's `value` is a `literal-string`,** wherever the case comes from, a parameter, a variable or a collection's element included, since every case's backing value is a literal. So the columns or tables a query may name are an enum, and the one chosen while the code runs reaches a plain PHP `literal-string` parameter, as Laravel's `orderByRaw` declares one, as one of the application's own names. Values still go through a query's bindings:

<!-- example allow-list-enums: statements
import Illuminate.Database.Query.Builder;
public enum SortColumn : string
{
    case Name = "name";
    case CreatedAt = "created_at";
}
Builder query = new Builder();
SortColumn column = SortColumn.CreatedAt;
string chosen = "created_at";
-->
```csharp
query.orderByRaw(column.value);                  // compiles: "created_at", one of the enum's own names
query.orderByRaw(SortColumn.Name.value);         // compiles
query.orderByRaw(chosen);                        // compile error: chosen is composed while the code runs
```

**A string fixed while the code compiles is a `literal-string` too,** as Psalm and PHPStan read one: a string literal, a constant, and a template or a `+` join whose parts are all literals or constants of type `int`, `float` or `string`:

<!-- example fixed-strings: statements
import Illuminate.Database.Query.Builder;
public enum Weights
{
    case One;

    public const int COUNT = 3;
    public const string LINEAR = `1.0 / ${Weights.COUNT} as weight`;
}
Builder query = new Builder();
string column = "created_at";
-->
```csharp
Weights.LINEAR;                                          // "1.0 / 3 as weight"
query.selectRaw(Weights.LINEAR);                         // compiles
query.selectRaw(`1.0 / ${Weights.COUNT} as weight`);     // compiles
query.selectRaw(`max(${column})`);                       // compile error: column is composed while the code runs
```

**In an application, a string fixed at compile time never selects.** `==`, `!=` and a `match` arm that compare a string with a `literal-string` pick one of a closed set of values, and a closed set of values is a backed enum, which `parse` reads where the data enters (section 20). So each is a compile error in a project, a package whose `composer.json` does not say `"type": "library"`, as section 5's rules are, and the error names both sides: "`==` compares `order.status` with `"paid"`, a string fixed at compile time, so it selects one of a closed set of values." Comparing with `""` tests for empty text, and its error names `isEmpty()` or `isNotEmpty()` (section 18). `equals` compares as `==` does, so `status.equals("paid")` and `"paid".equals(status, true)` are compile errors too. A search of a `List` or a `Set` compares each element by `==` (section 12), so `contains`, `indexOf` and `remove` select where the value is a `literal-string`, as `statuses.contains("paid")`, or where the collection is a literal of them, as `["paid", "due"].contains(order.status)`, and each is a compile error, while `tags.contains(tag)` compiles. `add` searches nothing, so `tags.add("new")` compiles. Two strings from data still compare as text, as `password == confirmation` does, and a text operation such as `line.startsWith("#")` or `text.split(",")` selects nothing. A library is exempt, and the check reads only declared types, never a string's contents:

```text
if (order.status == "paid") { }           error: a compile-time string in ==, != or a match arm
if (order.status == OrderStatus.Paid) { } compiles: the value is a backed enum
if (name == "") { }                       error: call name.isEmpty()
if (password == confirmation) { }         compiles: two strings from data
if (["paid", "due"].contains(order.status)) { }   error: a search for a compile-time string
if (tags.contains(tag)) { }               compiles: a search for a string from data
```

**In an application, a `Map` key is data.** A `literal-string` used as a `Map` key, read or written, as in `row["plan"]`, `row["plan"] = "pro"`, `row.get("plan")` or `row.set("plan", "pro")`, names a field of a fixed shape, which a struct declares (section 10), so it is a compile error in a project: "`"plan"` is a string fixed at compile time, so it names a field of a fixed shape, not a key of a `Map`." `Settings.parse(row).plan` reads the field. A key from data, as in `counts[tag]`, is a `Map`'s own.

**A member's name is not a string fixed at compile time.** `nameof(Order.total)` has the type `keyof Order` (section 11.2), which names a member, so `column == nameof(Order.total)` and `row[nameof(Order.total)]` compile in a project.

**`Object` holds any object, and nothing else.** It compiles to PHP's `object`, and `Object?` adds null. Like `Any`, it has no members until `is` narrows it. An `Object` passes where `Any` is expected, and a plain PHP value typed `object` arrives as `Object`.

<!-- example object-type -->
```php
namespace Vendor\Registry;

final class Handles
{
    public static function of(object $value): int { return spl_object_id($value); }
}
```

<!-- example object-type: statements
import Vendor.Registry.Handles;
extern Handles uses none;
-->
```csharp
public class Ids
{
    public static int identity(Object value) => Handles.of(value);   // compiles
    public static int identityOf(Any value) => Handles.of(value);    // compile error: Any may hold an int, and Handles.of takes an object

    public static void sync(Object source)
    {
        source.id;                              // compile error: check what source is first
        if (source is Order order) { … }        // compiles
    }
}

Ids.identity(5);                                // compile error: 5 is not an object
```

## 25. Referring to classes

**`typeof(X)`** is typed `Class<X>`, for any one type `X`. It works on every type parameter, a method's included, because every type argument reaches the running program (section 11). PHP's `Order::class` is removed.

<!-- example typeof-types
public class Example
{
    public void run<TItem>()
    {
        …
    }
}
-->
```csharp
Class<Order> type = typeof(Order);
type.attributes<Listen>();
typeof(TItem);
typeof(int);                    // the class value of int
typeof(int|string);             // the class value of int|string: a union is one type
typeof(List<Order>);            // the class value of a list of orders
typeof(Map<string, int>);
typeof(Self);                   // the class run was called on
```

**`typeof(TItem)` works for any type argument, including `int`.** It is a `Class<TItem>`, which can create objects, only when `TItem`'s bound is a class:

<!-- example typeof-type-parameters: members
import Illuminate.Support.Facades.Log;
-->
```csharp
public TItem make<TItem : Element>(string key)
{
    const type = typeof(TItem);
    return new (type)(key);                                      // compiles: TItem's bound is a class
}

public void log<TItem>(TItem value) { Log.info("type", ["class": typeof(TItem)]); }   // compiles, also when TItem is int

public TItem blank<TItem>()
{
    const type = typeof(TItem);
    return new (type)();                                         // compile error: TItem's bound is not a class
}
```

**`typeof` takes a type only. `Class.of(value)` gives an object's class.** For a value of type `T`, it is typed `Class<T>` and holds the object's runtime class, which may be a subclass of `T`.

<!-- example typeof-values: statements
import Illuminate.Support.Facades.Log;
Order order = new Order();
int id = 1;
-->
```csharp
Class<Order> type = Class.of(order);            // compiles: order's runtime class, which may be a subclass of Order
const fresh = new (type)(id);                   // compiles: a new object of that class
Log.info("saved", ["class": Class.of(order)]);  // compiles
typeof(order);                                  // compile error: typeof takes a type; write Class.of(order)
```

**`nameof(Counted)` gives a class's own name, and `name()` its full name with PHP#'s dots,** so a message names a class as PHP# code writes it:

<!-- example class-names -->
```csharp
// app/Orders/Counted.sharp
namespace App.Orders;

public class Counted { }
```

<!-- example class-names: statements
import App.Orders.Counted;
Counted row = new Counted();
-->
```csharp
nameof(Counted);                   // "Counted"
Class.of(row).name();              // "App.Orders.Counted"
typeof(Counted).name();            // "App.Orders.Counted"
```

**`properties()` reads the properties a class declares, `fields()` what its objects store, and `typeArguments()` a type's type arguments,** so an ORM, a serializer or a form builder reads a class's declared types without plain PHP reflection. A property is the class's API and a field is storage, so a form builder reads `properties()` and a storage layer reads `fields()`. `properties()` gives a `List<Property>` of every property, at every visibility, its statics included, an auto-property, a computed one and one with accessors, whose `get` and `set` run its accessors, and no field, the class's own first and then those it inherits, in the order they are declared. `fields()` gives a `List<Field>` of every value the class's objects and the class store, in the same order: each field, and the storage of each auto-property that is neither `virtual` nor `abstract` (section 6.1), which has no accessor body, as `{ get; set; }`, `{ get; private set; }`, `{ get; init; }` and a constructor-promoted one, and each property of a plain PHP class that declares no hook, at every visibility, whether or not a subclass may replace it. A computed property, one with an accessor body and a PHP# `virtual` or `abstract` one store nothing a `Field` reads, so they have none. The class value of a type that is no class, as `typeof(Object)`, `typeof(int)` or `typeof(List<int>)`, has no properties, methods or attributes. A `Property` has its `name`, its `type`, the declared type whole as a `Class<Any?>`, the `declaringClass` that declares it, its `visibility`, its `setVisibility`, the access modifier that reaches its `set` or `init`, or null for a get-only one, `isInitOnly`, which holds for one with an `init`, `isStatic`, `isVirtual`, which holds when a subclass may replace it, `isAbstract`, `isOverride`, `hasDefaultValue`, which holds for a declared initial value, constant or not, and `defaultValue()`, which evaluates it on each read, as `Parameter.defaultValue()` does, so `= new Draft()` gives a new `Draft` each time, and throws `OutOfRangeException` for a property without one, and `attributes<TAttribute : Object>()`, as `Class<T>`'s own. A `Field` has its `name`, `type`, `declaringClass`, `visibility`, `isStatic`, `hasDefaultValue`, `defaultValue()` and `attributes<TAttribute : Object>()`, as a `Property` has them, an auto-property's those of the property, and `isInitialized(receiver)`, `get(receiver)` and `set(receiver, value)`, which read and write the storage. A `Visibility` is `Public`, `Internal`, `Protected` or `Private`, the access modifier that declares the member (section 5), so an `internal` member reads `Internal` though plain PHP reaches it as a public one:

<!-- example declared-types -->
```csharp
// app/Shelf/Product.sharp
namespace App.Shelf;

import App.Field;

public class Product
{
    [Field] List<string> tags = [];
    [Field] int? stock = null;
    public string name { get; } = "tea";
    public string label => this.name.uppercase();
}
```

<!-- example declared-types: statements
namespace App.Checkout;
import App.Field;
import App.Shelf.Product;
-->
```csharp
const properties = typeof(Product).properties();
properties.map(property => property.name);                  // ["name", "label"]: a field is no property
properties[0].visibility == Visibility.Public;              // true
properties[0].setVisibility;                                // null: name is get-only
const fields = typeof(Product).fields();
fields.map(stored => stored.name);                          // ["tags", "stock", "name"]: label stores nothing
fields[0].type == typeof(List<string>);                     // true
fields[0].type.typeArguments() == [typeof(string)];         // true
fields[1].type == typeof(int?);                             // true
fields[1].attributes<Field>().count();                      // 1
fields[1].declaringClass == typeof(Product);                // true
fields[1].visibility == Visibility.Private;                 // true
typeof(Map<string, int>).typeArguments() == [typeof(string), typeof(int)];   // true
```

**`methods()` reads the methods a class declares.** It gives a `List<Method>` of every instance and static method, at every visibility, the class's own first and then those it inherits, in the order they are declared, without the constructor, which `constructor()` gives. It gives no method the compile makes for the engine, whose name holds `#`, as a class's `#fields`, and plain PHP's reflection, `get_class_methods()` and `method_exists()` find none either, since no PHP code can declare such a name. A `Method` has its `name`, its result `type` whole, `typeof(void)` for a `void` method and `typeof(never)` for a `never` one, which a `Class<void>` and a `Class<never>` hold, compare and key a `Map` as any class value, though no local is `void`, `isStatic`, `isVirtual`, `isAbstract`, `isOverride`, its `declaringClass` and `visibility`, as a `Property` has them, `attributes<TAttribute : Object>()`, and `parameters()`, a `List<Parameter>`. A `Parameter` has its `name`, `type`, `isOptional`, which holds when a call may leave it out, `isVariadic`, `hasConstantDefault`, which holds when it has a default that runs no code, `defaultValue()`, and `attributes<TAttribute : Object>()`. `defaultValue()` evaluates a default that runs no code, as `null`, `0`, `[]`, a constant or an enum case, and throws `ValueError` for one that runs code, a `new`, a call or a lambda, without running any of it, so a reader reads it under any `uses` (section 29). A reader that fills a call's missing arguments leaves such an argument out, and the call runs the default under its own effects. A parameter without a default throws `OutOfRangeException`, as `first` does when nothing matches. The type arguments of the class value stand for the class's type parameters, a class value without them reads each as the type parameter itself, and a plain PHP method gives its PHP types as PHP# writes them, as `properties()` does:

<!-- example declared-methods -->
```csharp
// app/Shelf/Crate.sharp
namespace App.Shelf;

public class Crate<TItem>
{
    public TItem? first(List<TItem> items, int limit = 1) => null;
}
```

<!-- example declared-methods: statements
import App.Shelf.Crate;
-->
```csharp
const methods = typeof(Crate<string>).methods();
methods[0].name;                                              // "first"
methods[0].type == typeof(string?);                           // true
methods[0].isStatic;                                          // false
methods[0].isVirtual;                                         // false: a PHP# method is closed unless `virtual`
methods[0].declaringClass.name();                             // "App.Shelf.Crate"
methods[0].visibility == Visibility.Public;                   // true
methods[0].parameters().map(parameter => parameter.name);     // ["items", "limit"]
methods[0].parameters()[0].type == typeof(List<string>);      // true
methods[0].parameters()[1].isOptional;                        // true
methods[0].parameters()[1].isVariadic;                        // false
methods[0].parameters()[1].hasConstantDefault;                // true
methods[0].parameters()[1].defaultValue();                    // 1
```

**A class value takes any type apart,** so the type a member declares reads whole, as `typeof` gives it, and never as a text to parse. `kind()` gives its `TypeKind`: `Named` for a type with a name and its arguments, as `int`, `List<int>` and `Crate<Mark>`, `Union`, `Intersection`, `Tuple`, `Function` or `TypeParameter`. `isNullable()` holds for `T?` and a union that holds null, and `nonNullable()` gives the type without it. Each other member reads the type without its `?`: `typeArguments()`, each whole, and `genericDefinition()`, the generic type with no type arguments, for a named type; `unionMembers()` for a union, which never holds null; `intersectionMembers()` for an intersection, as `[typeof(Billable), typeof(Shippable)]` for `Shippable & Billable`, in the order PHP# spells the type; `tupleElements()`, each a `TupleElement` with its `name`, null for an element written without one, and its `type`; `parameterTypes()` and `returnType()` for a function type; and `bound()` for a type parameter, `Any?` where none is written, since such a type parameter takes `int` too (section 11). `typeParameters()` gives a generic type's type parameters, and a type parameter's `name()` is its own. A member of another kind gives `[]`, or throws `OutOfRangeException` where it gives one value. `makeGenericType(arguments)` gives the type with its type arguments, and throws `InvalidArgumentException` on a count or a bound they break:

<!-- example taking-types-apart -->
```csharp
// app/Shelf/Holder.sharp
namespace App.Shelf;

public class Holder<TItem>
{
    public [string method, int count] pair { get; set; } = ["get", 1];
    public Function<int(string)>? reader { get; set; } = null;
    public TItem? item { get; set; } = null;
}
```

<!-- example taking-types-apart: statements
import App.Shelf.Holder;
-->
```csharp
const properties = typeof(Holder).properties();
properties[0].type.tupleElements().map(element => element.name);   // ["method", "count"]
properties[1].type.isNullable();                                   // true
properties[1].type.nonNullable().kind() == TypeKind.Function;      // true
properties[1].type.parameterTypes() == [typeof(string)];           // true
properties[2].type.nonNullable().name();                           // "TItem"
typeof(Holder).typeParameters()[0].bound() == typeof(Any?);        // true
typeof(List<int>).genericDefinition() == typeof(List);             // true
typeof(List).makeGenericType([typeof(int)]) == typeof(List<int>);  // true
typeof(int?).nonNullable() == typeof(int);                         // true
```

**A class value reads every fact its class's declaration states:** `baseType()`, the class it extends with the type arguments it gives it, or null; `interfaces()`, each with its type arguments; `traits()`, the plain PHP traits it, the classes it extends and those traits use, each once (section 22); `isAbstract()`, `isInterface()`, `isStruct()` and `isEnum()`; and an enum's `enumBackingType()`, null for a pure enum, and `enumCases()`, each an `EnumCase` with its `name`, its `value`, the case itself, its `backingValue`, null for a pure enum's, and `attributes<TAttribute : Object>()`:

<!-- example declared-facts -->
```csharp
// app/Shelf/Level.sharp
namespace App.Shelf;

public enum Level : int
{
    case Low = 1;
    case High = 2;
}
```

<!-- example declared-facts: statements
import App.Shelf.Level;
-->
```csharp
typeof(Level).isEnum();                                       // true
typeof(Level).enumBackingType() == typeof(int);               // true
typeof(Level).enumCases().map(found => found.name);           // ["Low", "High"]
typeof(Level).enumCases()[1].backingValue;                    // 2
typeof(Level).enumCases()[1].value as Level == Level.High;    // true
typeof(Level).baseType();                                     // null
```

**`constants()` reads the constants a class declares.** It gives a `List<Constant>` of every constant, at every visibility, the class's own first and then those it inherits, in the order they are declared, without an enum's cases, which `enumCases()` gives. A `Constant` has its `name`, its `type`, the declared type whole, `Any?` for one declared without a type, the `declaringClass` that declares it, `value()`, which gives its value as `Any?`, and `attributes<TAttribute : Object>()`. `value()` reads the constant as a direct read from the calling code would, so a private one only from its class, which throws `Error` elsewhere, and a `Map` or `Set` constant gives the object a direct read gives:

<!-- example declared-constants -->
```csharp
// app/Shelf/Limits.sharp
namespace App.Shelf;

public class Limits
{
    public const int MAX = 10;
    public const Map<string, int> PER_SIZE = ["small": 2, "large": 5];
    private const string CODE = "lim";
}
```

<!-- example declared-constants: statements
import App.Shelf.Limits;
-->
```csharp
const constants = typeof(Limits).constants();
constants.map(constant => constant.name);                     // ["MAX", "PER_SIZE", "CODE"]
constants[0].type == typeof(int);                             // true
constants[0].value();                                         // 10
constants[1].type == typeof(Map<string, int>);                // true
constants[1].value() as Map<string, int> == Limits.PER_SIZE;  // true
constants[2].declaringClass == typeof(Limits);                // true
constants[2].value();                                         // throws Error
```

**`constructor()` and `createInstance` reach a class's constructor through its class value.** `constructor()` gives a `Constructor`, with its `visibility`, `parameters()` and `attributes<TAttribute : Object>()`, or null for a class without one. `createInstance(arguments)` creates an object of the class, with the class value's type arguments, by its constructor with the `List<Any?>` of arguments, which the calling code reaches as a direct `new` would. It throws `InvalidArgumentException` for an abstract class, an interface or an enum, and for arguments the constructor's parameters don't take. A `Property` reads the state of such an object:

- **`isInitialized(receiver)`** holds when the property holds a value, so it is false for a `late` property no code has set. A `late` property is set once, so there is no reset.
- **`getter()` and `setter()`** give the property's `get` and `set` accessors as a `Method`, named `$name::get` and `$name::set` as PHP names a property hook, or null for a plain stored property. That is how code tells a property with accessors apart. Their `createDelegate` and `createInvoker` give a function that reads or writes the property, as `get` and `set` do.

<!-- example reflected-creation -->
```csharp
// app/Shelf/Account.sharp
namespace App.Shelf;

public class Account
{
    public int id { get; set; } = 0;
    public late string owner { get; set; }
    public int doubled => this.id * 2;

    public Account(string owner) { this.owner = owner; }
}
```

<!-- example reflected-creation: statements
import App.Shelf.Account;
-->
```csharp
const made = typeof(Account).createInstance(["ada"]);
made.owner;                                                   // "ada"
typeof(Account).createInstance([]);                           // throws InvalidArgumentException
const properties = typeof(Account).properties();
properties[0].getter();                                       // null: id is stored
properties[2].getter()?.name;                                 // "$doubled::get"
properties[1].isInitialized(made);                            // true: the constructor set owner
```

**`withTag(name)` gives the variant of a family whose `[Key]` is `name`** (section 10), as a class value of the family, or null for a name the family has no variant of and for a class that is no family. It is pure, so a single-table-inheritance base builds the class a row stores with `new (typeof(Self).withTag(row.kind) ?? throw new ValueError(…))(…)`, which needs the base's constructor to be `required` (section 9).

<!-- example with-tag -->
```csharp
// app/Shipping/Shipment.sharp
namespace App.Shipping;

import Sharp.Tag;

[Tag("kind")]
public abstract class Shipment
{
    public required Shipment(public string label { get; }) { }

    public static Self fromRow(string kind, string label)
        => new (typeof(Self).withTag(kind) ?? throw new ValueError(`No shipment is of the kind ${kind}.`))(label);
}
```

<!-- example with-tag -->
```csharp
// app/Shipping/Letter.sharp
namespace App.Shipping;

import Sharp.Key;

[Key("letter")]
public class Letter : Shipment { }
```
<!-- run with-tag
import App.Shipping.Shipment;
Shipment.fromRow("letter", "to Bo").label;   // "to Bo"
Shipment.fromRow("crate", "none");           // throws: "No shipment is of the kind crate."
-->

<!-- example class-tag: statements
import Sharp.Key;
import Sharp.Tag;
public machine JourneyStatus : string
{
    case Open = "open" { public JourneyStatus.Completed complete() => JourneyStatus.Completed; }
    final case Completed = "completed";
    public static JourneyStatus.Open enter() => JourneyStatus.Open;
}
[Tag("type")]
public sealed interface Tender { }
[Key("card")]
public struct Card : Tender { public Card(public string last4 { get; }) { } }
public class Example
{
    public void run()
    {
        …
    }
}
-->
```csharp
typeof(JourneyStatus.Completed).tag();              // "completed"
typeof(JourneyStatus).withTag("completed");         // typeof(JourneyStatus.Completed)
typeof(Card).tag();                                 // "card", from [Key("card")]
typeof(JourneyStatus).tag();                        // null: the machine is no variant
```

<!-- example class-decode: statements
public machine LinkStatus : string
{
    case Draft = "draft" { public LinkStatus.Published publish() => LinkStatus.Published; }
    final case Published = "published";
    public static LinkStatus.Draft enter() => LinkStatus.Draft;
}
public class Page
{
    public Page(public string title { get; }) { }
    public LinkStatus status { get; private set; } = LinkStatus.enter();
}
-->
```csharp
const row = ["status": "draft", "title": "Home"];
typeof(Page).decode(row);                       // a Page whose status is LinkStatus.Draft, read by its tag
typeof(Page).decode(["title": 5]);              // throws InvalidFields: title: expected string, got int 5
```

**Each reflection value keys a `Map` and joins a `Set` as the member it reflects,** however often reflection read it. Two `Method`s, two `Property`s, two `Field`s, two `Constant`s and two `Constructor`s are equal when one class declares them under one name, two `Parameter`s when they are one position of one method, two `EnumCase`s when they are one case of one enum, and two `TupleElement`s when they have one name, or none, and one type. Equal values share their `hash()`, so `Map<Method, List<Relation>>` compiles:

<!-- example reflection-keys: statements
public class Ledger { public int total { get; set; } = 0; public int add(int amount) => this.total + amount; }
-->
```csharp
typeof(Ledger).methods()[0] == typeof(Ledger).methods()[0];   // true
Map<Method, List<string>> uses = [typeof(Ledger).methods()[0]: ["audit"]];
uses[typeof(Ledger).methods()[0]] ?? [];                      // ["audit"]
Map<Property, int> reads = [typeof(Ledger).properties()[0]: 1];
reads[typeof(Ledger).properties()[0]] ?? 0;                   // 1
```

**A class value of a built-in generic type answers `is` as a user generic's does,** so `typeof(List<string>)` is a `Class<List>`, a `Class<List<Any?>>` and a `Class<Iterable<Any?>>`, and a `Map`'s and a `Set`'s class values are a `Class<Map>`, a `Class<Set>` and a `Class<Iterable<Any?>>`, as `typeof(Crate<Mark>)` is a `Class<Crate>`.

**`get` and `set` use a property through its reflection, and `createDelegate` and `createInvoker` call a method through its.** They are the one way PHP# names a member by a value (decision 94). `property.get(receiver)` gives the property's value as `Any?`, running a computed property's `get`, and `property.set(receiver, value)` writes it. Each reaches the property as a direct use from the calling code would: a private member only from its class, a protected one from the class and its subclasses, and an `internal` one from its namespace and those below it, which plain PHP reaches as a public one. A use the calling code can't make throws `Error`, as does a write of a get-only property. Each value is checked against its type as a direct PHP# use's is, so a wrong one throws `TypeError`, and so does a receiver that isn't of the property's class. `property.isInitialized(receiver)` and `property.hasDefaultValue` only read the declaration and the object, so each is pure. `get` and `set` each run code its caller can't see, a `get` or a `set`, so each may have every effect (section 29), and code that must keep to its `uses`, as an implementation of a pure interface method, can't call it. A `Field` is how that code reads and writes what an object stores:

<!-- example reflection-calls -->
```csharp
// app/Shelf/Counter.sharp
namespace App.Shelf;

public class Counter
{
    public int count { get; set; } = 1;
    public int doubled => this.count * 2;
}
```

<!-- example reflection-calls: statements
import App.Shelf.Counter;
-->
```csharp
const counter = new Counter();
const properties = typeof(Counter).properties();
properties[0].set(counter, 5);
properties[0].get(counter);                                   // 5
properties[1].get(counter);                                   // 10
properties[1].set(counter, 4);                                // throws Error
```

**`createDelegate<TFunction>()` makes a method a value of the function type `TFunction`, so a call through reflection keeps to a `uses`.** `method.createDelegate<Function<Relation(Order) uses Database>>()` gives a function that calls the method. An instance method takes its receiver as the type's first parameter, of the class value `methods()` read it from, of a class below it, or of a class it extends, `Object` included, so a base class walks a subclass's methods with its own type, and a call with a receiver of another class throws `TypeError`. A static method takes no receiver and runs on that class value. `property.getter()` and `property.setter()` give their accessor's value the same way, so `getter().createDelegate<Function<int(Order)>>()` reads a computed property. The method fits the type as a function assigned to it must (section 21): the type passes each parameter the method needs, each parameter type it passes is within the method's, the method's result is within the type's unless that is `void` or `Any?`, and the type's `uses` lists each effect the method's body has (section 29). A plain PHP method has the effects its `extern` line declares, read as a call reads them, and every effect when the line says `uses any`, so `Function<string(RuntimeException)>` holds `getMessage`, which the standard library declares pure. A method that doesn't fit throws `ValueError` that names it and the reason, as "`App.Shop.Order.notify` has the effect `App.Shop.Mailer`, and `Function<int(App.Shop.Order) uses Sharp.Data.Database>` allows only `Sharp.Data.Database`.", and so does a call on a receiver whose override has an effect the type doesn't list. So a call of the value has only the effects its type lists, the checker types the call by the type alone, and `createDelegate` itself is pure. The calling code reaches the method as a direct call would when it makes the value:

<!-- example reflection-delegates -->
```csharp
// app/Shop/Related.sharp
namespace App.Shop;

[Attribute]
public class Related
{
}
```

<!-- example reflection-delegates -->
```csharp
// app/Shop/Relation.sharp
namespace App.Shop;

public class Relation
{
    public Relation(public string name { get; }) { }
}
```

<!-- example reflection-delegates -->
```csharp
// app/Shop/Relations.sharp
namespace App.Shop;

public interface Relations
{
    List<Relation> relations() uses Database;
}
```

<!-- example reflection-delegates -->
```csharp
// app/Shop/Model.sharp
namespace App.Shop;

public abstract class Model : Relations
{
    public List<Relation> relations()
        => typeof(Self).methods()
            .filter(method => method.attributes<Related>().count() > 0)
            .map(method => method.createDelegate<Function<Relation(Model) uses Database>>()(this));   // compiles: each call has only Database
}
```

**`createInvoker<TFunction>()` makes a method a value that takes its arguments as one list, so a dispatcher that calls methods of their own parameters keeps to a `uses`.** `method.createInvoker<Function<Any?(Object, List<Any?>) uses Database>>()` gives a function that calls an instance method on the receiver it takes first with the `List<Any?>` of arguments, and `Function<Any?(List<Any?>)>` one that calls a static method on the class value `methods()` read it from. The method fits `TFunction` as it fits `createDelegate`'s, by its receiver, its result and its effects, and one that doesn't fit throws the same `ValueError`, but the type passes the one list in place of the method's parameters. A call checks each value of the list against the parameter at its place, as a direct call does, so a wrong one throws `TypeError` that names the parameter. A parameter the list leaves out takes its default when the default runs no code, as `Parameter.defaultValue()` reads it. The call throws `ValueError` for a default that runs code, which it never runs, and `ArgumentCountError` for a required parameter the list leaves out or a value past the last parameter. So a call of the value has only the effects its type lists, its result is within the type's, and `createInvoker` itself is pure:

<!-- example reflection-invokers -->
```csharp
// app/Shop/Billing.sharp
namespace App.Shop;

public class Billing
{
    public string pause(string reason, int days) => `${reason} for ${days} days`;

    public static string create(string name, string plan = "normal") => `${name} on ${plan}`;
}
```

<!-- example reflection-invokers: statements
import App.Shop.Billing;
-->
```csharp
const billing = new Billing();
const pause = typeof(Billing).methods().first(method => method.name == "pause");
const create = typeof(Billing).methods().first(method => method.name == "create");
const paused = pause.createInvoker<Function<string(Object, List<Any?>) uses Database>>();
const created = create.createInvoker<Function<string(List<Any?>)>>();
paused(billing, ["moving", 30]);                              // "moving for 30 days"
paused(billing, [30, "moving"]);                              // throws TypeError
paused(billing, ["moving"]);                                  // throws ArgumentCountError
created(["Acme"]);                                            // "Acme on normal"
created(["Acme", "pro", 3]);                                  // throws ArgumentCountError
pause.createInvoker<Function<string(List<Any?>)>>();          // throws ValueError
```

**A `Field` reads and writes storage, so it runs no code a project wrote.** `field.get(receiver)` and `field.set(receiver, value)` reach the storage as a direct use from the calling code would, with the access and type checks of `Property.get` and `set`, an auto-property's storage as its `get`, and its `set` or `init`, do. An `init` property's storage takes a write only while it holds no value, as only the object's creation writes it, and a write after that throws `Error`. They run no accessor, so `field.get`, `field.set` and `field.isInitialized` are pure, and a storage layer held to `uses Database` reads and writes a row through `fields()`. A receiver whose class gives the property an accessor, as a PHP 8.4 subclass may by declaring it again, throws `Error` from `get` and `set`, which run neither accessor. A static's `get` and `set` throw `Error` before its class has run its initial values. `field.defaultValue()` runs an initial value that is not constant, so it has the effects of the initial values it can run, as `property.defaultValue()` has (section 29). Reading the storage never makes a `Property` pure: `property.get` still runs whatever accessor the property has:

<!-- example reflection-effects -->
```csharp
// app/Shelf/Audit.sharp
namespace App.Shelf;

public interface Audit
{
    Any? read(Object target);
}
```

<!-- example reflection-effects -->
```csharp
// app/Shelf/FieldAudit.sharp
namespace App.Shelf;

public class FieldAudit : Audit
{
    private int count = 1;

    public Any? read(Object target) => typeof(FieldAudit).fields()[0].get(target);   // compiles: a field's read runs no code
}
```

<!-- example reflection-effects -->
```csharp
// app/Shelf/PropertyAudit.sharp
namespace App.Shelf;

public class PropertyAudit : Audit
{
    public int count { get; } = 1;

    public Any? read(Object target) => typeof(PropertyAudit).properties()[0].get(target);   // compile error: read must be pure, and get may have every effect
}
```

**A class value creates and reflects the class it holds, which code picks while it runs.** `typeof` and `Class.of` give one, and a `Class<T>` field, parameter or local holds one:

<!-- example class-values -->
```csharp
// app/Element.sharp
namespace App;

public abstract class Element
{
    public required Element(string key) { … }
    public static string defaultTag() => "div";
}
```

<!-- example class-values -->
```csharp
// app/FormBuilder.sharp
namespace App;

import Illuminate.Database.Eloquent.Model;

public class FormBuilder
{
    Map<string, Class<Element>> elements = ["form": typeof(FormElement), "input": typeof(InputElement)];

    public Element make(string kind, string key)
    {
        const type = this.elements[kind] ?? throw new UnknownElement(kind);   // Class<Element>, with no type written
        return new (type)(key);                                                // compiles: Element's constructor is required
    }

    public string tagFor(string kind)
    {
        const type = this.elements[kind] ?? throw new UnknownElement(kind);
        const defaultTag = type.methods().first(method => method.name == "defaultTag");
        return defaultTag.createInvoker<Function<string(List<Any?>)>>()([]);   // runs defaultTag on the class type holds
    }

    public TModel? load<TModel : Model>(int id) => TModel.find(id);           // calls find on the type argument's class
}
```

- **`new (type)(…)`** creates an object from a class value. The parentheses around `type` mark it as an expression, not a class name. It needs a `required` constructor, as `new Self(…)` does. For a plain PHP class, such as `Class<Model>`, the checker checks the call against that class's own constructor.
- **A class value reflects, and reaches no static.** A static is reached through its class's name, `Element.defaultTag()`, or through a type parameter, `TModel.find(id)` (section 11). The static of a class known only while the code runs is reached through `methods()` and `createInvoker`. `type.defaultTag()` is a compile error.
- **`Class`'s own members are what a class value reaches,** such as `attributes`. Any code may extend `Class<T>`, and `Class`'s own members still win over an extension's.
- **`attributes<TAttribute : Object>()` reads attributes, which are objects,** so `attributes<int>()` is a compile error.
- **A class value's type need not be written.** `const type = …` holds a `Class<Element>`.
- **A property named by a variable has no form.**

<!-- example class-members: statements
Class<Element> type = typeof(FormElement);
string kind = "form";
string key = "signup";
-->
```csharp
type.attributes<Listen>();                       // Class's own method
type.attributes<int>();                          // compile error: int is not an Object
type.defaultTag();                               // compile error: a class value reflects, and calls no static
new (kind)(key);                                 // compile error: kind is a string, not a Class
```

**A class value of a generic class can carry its type arguments or leave them out.** `typeof(PaginatedList<Order>)` is a `Class<PaginatedList<Order>>`, and `new (pages)()` creates a `PaginatedList<Order>`. A `Class<PaginatedList>` leaves them out, so `new` names them, `new (any)<Order>()`, or takes them from its arguments or the declared type its object goes into, as any `new` does (section 11). `new (any)()` that nothing fixes them for is a compile error.

<!-- example generic-class-values -->
```csharp
public class Pages
{
    public PaginatedList<Order> fresh()
    {
        Class<PaginatedList<Order>> pages = typeof(PaginatedList<Order>);
        return new (pages)();                                    // compiles: a PaginatedList<Order>
    }

    public PaginatedList<Order> ofOrders(Class<PaginatedList> any) => new (any)<Order>();   // compiles: a PaginatedList<Order>
    public PaginatedList<Order> declared(Class<PaginatedList> any) => new (any)();         // compiles: the declared type gives Order
    public Any open(Class<PaginatedList> any) => new (any)();                              // compile error: nothing fixes TItem, so write it
}
```

**Two class values are equal when they name the same class with the same type arguments,** so `typeof(Box<Order>) == typeof(Box<Invoice>)` is false:

<!-- example class-value-equality: statements
public class Box<T> { }
public class Invoice { }
-->
```csharp
typeof(Box<Order>) == typeof(Box<Order>);       // true
typeof(Box<Order>) == typeof(Box<Invoice>);     // false: the same class with other type arguments
```

**`Self`** means the class a static method was actually called on. It replaces PHP's `static`. PHP's `self` is removed: to mean the declaring class, write its name.

<!-- example self-return: statements
public class Order : DatabaseEntity { public Order(Row row) : super(row) { } }
Any value = 1;
VerifiedUser caller = new VerifiedUser();
-->
```csharp
public abstract class DatabaseEntity
{
    public required DatabaseEntity(Row row) { … }

    public static Self fromSchema(Any value, VerifiedUser caller)
    {
        Row row = …;
        return new Self(row);
    }
}

const order = Order.fromSchema(value, caller);   // typed as Order
```

`Self` replaces the workaround of passing a class to itself, as in `class Order : Entity<Order>`.

`Self` is written only as a return type, and in bodies as `new Self(…)`, `Self.m()`, `Self.name` and `typeof(Self)`, the class value of the class the method was called on, which replaces PHP's `static::class`. A `Self` parameter is a compile error, because a subclass would accept any parent:

<!-- example self-parameter: members -->
```csharp
public bool same(Self other) { … }   // compile error: Self is a return type only
```

**`Self.name` reads and writes a constant or a static member of the class the code was called on,** so a subclass's own constant answers a call made on the subclass, and plain PHP calling the method on the subclass gets the same answer.

- **A parameter's default runs on the class the call ran on,** so `string table = Self.TABLE` gives a call on the subclass the subclass's `TABLE`.
- **A pattern of `Self.TABLE` tests the value of the class the code was called on,** as `Store.TABLE` tests `Store`'s.
- **A private member is the declaring class's own,** and a subclass has none, so `Self.KEY` on a private `KEY` is a compile error that names `Store.KEY`. A `protected` member is each subclass's.
- **A static method's value keeps its class's type arguments,** so `Counter<int>.bump` and `Self.bump` in `Counter<T>` run `bump` with them, as a call does.
- **A constant's value reads no class a call picks,** so `Self.name` in one is a compile error:

<!-- example self-members -->
```csharp
// app/Tables/Store.sharp
namespace App.Tables;

public class Store
{
    public const string TABLE = "items";
    public const string LABEL = Self.TABLE;                  // compile error: a constant reads no class a call picks
    private const string KEY = "store";
    public static int opened { get; set; } = 0;

    public static string table()
    {
        Self.opened += 1;
        return Self.TABLE;                                   // the TABLE of the class table was called on
    }

    public static string pick(string table = Self.TABLE) => table;   // the TABLE of the class pick was called on
    public static bool holds(Any value) => value is Self.TABLE;     // tests the TABLE of the class holds was called on
    public static string key() => Self.KEY;                  // compile error: a subclass has no private KEY: write Store.KEY
}
```

<!-- example self-members -->
```csharp
// app/Tables/OrderStore.sharp
namespace App.Tables;

public class OrderStore : Store
{
    public const string TABLE = "orders";
}
```

<!-- example self-members: statements -->
```php
App\Tables\Store::table();        // "items"
App\Tables\OrderStore::table();   // "orders": plain PHP called it on the subclass
App\Tables\Store::$opened;        // 2
App\Tables\OrderStore::pick();    // "orders"
App\Tables\OrderStore::holds("orders");   // true
```

**`new Self(…)` compiles only when the class's constructor is marked `required`, or no class can extend the class.** `Self` can be any subclass, so every class below it keeps a constructor that `new Self(…)` can call:

- its leading parameters match the parameters of the `required` constructor
- every parameter it adds has a default

<!-- example required-constructors
public abstract class DatabaseEntity { public required DatabaseEntity(Row row) { } }
…
-->
```csharp
// app/Order.sharp
namespace App;

import Sharp.Time.Clock;

public class Order : DatabaseEntity
{
    public Order(Row row, Clock? clock = null) : super(row) { … }   // compiles: new Self(row) can call it
}
```

<!-- example required-constructors -->
```csharp
// app/Invoice.sharp
namespace App;

import Sharp.Time.Clock;

public class Invoice : DatabaseEntity
{
    public Invoice(Row row, Clock clock) : super(row) { … }         // compile error: new Self(row) cannot supply clock
}
```

`required` is optional. A class that never writes `new Self(…)` never writes it.

**A `final` class or a struct needs no `required` constructor for `new Self(…)`.** No class extends it, so `Self` is the class itself, and `new Self(…)` calls its own constructor:

<!-- example final-self -->
```csharp
// app/Voucher.sharp
namespace App;

public final class Voucher
{
    public Voucher(public int cents { get; }) { }
    public Self doubled() => new Self(this.cents * 2);   // compiles: no class extends Voucher
}
```

<!-- example final-self -->
```csharp
// app/Memo.sharp
namespace App;

public class Memo
{
    public Memo(public string text { get; }) { }
    public Self copied() => new Self(this.text);         // compile error: a subclass of Memo may lack a constructor new Self(text) can call
}
```

## 26. Extension methods

An extension adds methods and properties to an existing type from outside it. Extensions are declared in `extension(...)` blocks inside a static class:

<!-- example extensions -->
```csharp
// app/Shared/Text/TextExtensions.sharp
namespace App.Shared.Text;

import Illuminate.Support.Str;

public static class TextExtensions
{
    extension(string value)
    {
        public string slug() => Str.slug(value);
        public bool isBlank => value.trim() == "";
    }
}
```

<!-- example extensions: statements
string title = "Spring Sale";
-->
```csharp
import App.Shared.Text.TextExtensions;

title.slug();
title.isBlank;
TextExtensions.slug(title);         // the same call, written through the class
```

- **An extension applies only in files that import its class, and in the files of its own namespace.** An extension the file imports, or one its own namespace declares, shadows a default extension of the same name, as a class does (section 23).
- **An extension member named like a member its type, or a class above it, already declares is a compile error where the extension is written,** because it can never run. On a union receiver, such as `Order|string`, it is an error only when every type of the union declares the member, because otherwise the extension runs on the rest:

<!-- example extension-hidden-member -->
```csharp
// app/Billing/OrderNames.sharp
namespace App.Billing;

import App.Order;

public static class OrderNames
{
    extension(Order order)
    {
        public string label() => "order";   // compile error: Order already declares label(), so this extension member never runs
    }
}
```

- **An extension is chosen from the type the line shows.** A member a subclass declares never makes an extension a compile error:

<!-- example extension-static-choice: statements
namespace App.Checkout;
import App.Order;
import App.RushOrder;
-->
```csharp
import App.Billing.OrderLabels;     // extension(Order order) { public string caption() => …; }

Order order = new RushOrder();      // RushOrder : Order declares its own caption()
order.caption();                    // compiles: the line shows an Order, so OrderLabels runs
```

- **An extension reaches what code in its own namespace reaches,** so access rules still hold: a `private` member stays out of reach, and an `internal` member of its namespace is in reach.
- **The receiver's type picks among the extensions with one name that apply in a file.** When several fit, the one whose receiver type is the most specific runs, so an extension on `RushOrder` wins over one on `Order` for a `RushOrder`. Two that fit where neither receiver type is inside the other are a compile error:

<!-- example extension-overloads: statements
namespace App.Checkout;
import App.Line;
import App.Money;
List<Line> lines = [new Line()];
Map<string, Money> prices = ["pro": new Money(2900, "USD")];
-->
```csharp
import App.Billing.LineTotals;      // extension(List<Line> lines) { public Money total() => …; }
import App.Billing.PriceTotals;     // extension(Map<string, Money> prices) { public Money total() => …; }

lines.total();                      // compiles: lines is a List<Line>, so LineTotals runs
prices.total();                     // compiles: prices is a Map<string, Money>, so PriceTotals runs
```

- **An extension block can declare type parameters,** so one block serves a receiver of any type argument, `extension<TItem>(List<TItem> items)`, within the type parameters' bounds (section 11). A call infers them from its receiver. A member's own type parameters come after the block's, and a call writes only those:

<!-- example generic-extensions -->
```csharp
// app/Shared/Lists/ListExtensions.sharp
namespace App.Shared.Lists;

import App.DatabaseEntity;

public static class ListExtensions
{
    extension<TItem : Equatable>(List<TItem> items)
    {
        public bool holds(TItem wanted) => items.contains(wanted);
        public List<TOut> mapped<TOut>(Function<TOut(TItem)> map) => items.map(map);
    }

    extension<TEntity : DatabaseEntity>(List<TEntity> rows)
    {
        public int saved => rows.count();
    }
}
```

<!-- example generic-extensions: statements
namespace App.Checkout;
import App.Order;
List<Order> orders = [new Order()];
List<string> names = ["spring"];
-->
```csharp
import App.Shared.Lists.ListExtensions;

orders.holds(new Order());              // compiles: TItem is Order, from orders
orders.mapped<string>(o => "order");    // compiles: a List<string>, the call writes only TOut
orders.saved;                           // compiles: an Order is a DatabaseEntity
names.saved;                            // compile error: string is outside TEntity's bound, so List<string> has no saved
```

- **Each type parameter of an extension block appears in its receiver's type,** because the receiver is the only place a call infers it from:

<!-- example extension-unused-type-parameter -->
```csharp
// app/Shared/Lists/Pairs.sharp
namespace App.Shared.Lists;

public static class Pairs
{
    extension<TItem, TOther>(List<TItem> items)    // compile error: the receiver List<TItem> doesn't use TOther
    {
        public int size() => items.count();
    }
}
```

- **An extension that changes the collection it is called on takes its receiver by `ref`,** as in `extension(ref List<int> counts) { public void bump() => counts.add(1); }`.

## 27. PHP# files

A file's extension marks it as PHP#. The engine, Composer's autoloader, editors and the checker all choose the grammar from the filename.

A PHP# file has no PHP tags, so `<?php`, `<?`, `<?=` and `?>` are compile errors. Its first line is code, after the byte order mark an editor may save, or a program's `#!` line:

<!-- example file-header -->
```csharp
namespace App.Tenant.Store;

import App.Shop.Records.Entities.DatabaseEntity;
```

The extension is **`.sharp`**, as in `StoreService.sharp`.

**A `.sharp` file that declares a type declares one, named like the file, case included.** Every type, events and interfaces included, sits at file level in the namespace its folder names (section 5), so a reader finds a type by its namespace and its file. A second type in a file is a compile error, and so is a `partial` class, because a class lives in one file. An `extern` declaration file (section 29) and a `Module.sharp` file (section 32) hold no type, so the rule doesn't cover them:

<!-- example one-type-per-file -->
```csharp
// app/Tenant/Store/StoreService.sharp
namespace App.Tenant.Store;

public class StoreService { … }       // compiles: StoreService.sharp declares StoreService
public class StoreHelper { … }        // compile error: move StoreHelper to StoreHelper.sharp
```

Plain PHP files keep `.php` and `<?php`, and the two call each other freely. Composer's autoloader tries `.sharp` after `.php`.

**A `.sharp` file whose class declares `main` runs as a program.** PHP runs it by its path, as in `php app/Console/Import.sharp data.csv`, or through its `#!` line, as in `./app/Console/Import.sharp data.csv`:

<!-- example program -->
```csharp
#!/usr/bin/env php
namespace App.Console;

import Sharp.System.Environment;

public class Import
{
    private Environment environment;

    public Import(Environment environment)
    {
        this.environment = environment;
    }

    public int main()
    {
        if (this.environment.arguments.count() < 2) {
            return 64;
        }

        return 0;
    }
}
```

- `main` is `public int main()` or `public void main()`. It takes no parameters, and any other `main` is a compile error.
- When PHP runs the file as its script, the CLI's script, a server's script or `auto_prepend_file`, the engine builds the class through the container, as it builds a class that declares an `on` method, with no module binding, and calls `main`. Before it builds the class, the engine loads the project's Composer autoloader.
- An `int` result is the process's exit code. A `void` `main` returns to PHP, as a web front controller that names the `.sharp` file as the server's script does, and as a prepend does.
- A file that `include` or the autoloader loads only declares its class.
- A `#!` line is allowed as a `.sharp` file's first line only, and is skipped. A `#!` anywhere else is a compile error.
- The program reads its arguments from `Environment.arguments`, and exits from elsewhere with `Process.exit`.

**`Position` says where code sits in its source.** PHP's magic constants `__DIR__`, `__FILE__`, `__LINE__`, `__FUNCTION__`, `__METHOD__`, `__NAMESPACE__` and `__CLASS__` are removed, along with every other `__Something__` form. `Position` is a standard-library type, imported by default (section 23). It has `file`, `directory`, `line`, `column` and `function`. `function` is the fully qualified dotted name, as in `App.Reports.logSlow`, because a short name is ambiguous across namespaces.

<!-- example position: statements -->
```csharp
namespace App;

import Illuminate.Support.Facades.Log;
import App.Store.Order;

public class Reports
{
    public string stubsFolder()
    {
        const stubs = Position.current().directory + "/stubs";   // the folder that holds this .sharp file
        return stubs;
    }

    public static void logSlow(string message, Position caller = Position.current())
    {
        Log.warning(message, ["file": caller.file, "line": caller.line, "function": caller.function]);
    }

    public static void logLine(string message, int line = Position.current().line) { }   // compiles: the caller's line
    public static void logNext(string message, int next = Position.current().line + 1) { }   // compile error: computed from the position

    public void run()
    {
        Reports.logSlow("slow query");                           // logs this call's own file and line, and "App.Reports.run"
        const log = Reports.logSlow;
        log("slow query");                                       // logs where logSlow declares caller, and "App.Reports.logSlow"
    }
}

const here = __FILE__;                                           // parse error
Log.info("charged", ["class": typeof(Order)]);                   // compiles: replaces __CLASS__
```

- `Position.current()` in a body gives the position where it is written.
- As a parameter's default, `Position.current()` gives the caller's position.
- A call through a function value gives the position of the parameter's declaration, because the compile fills the default with the caller's position only at a direct call, where it sees the function called.
- The parameter takes the whole position, or one field of it, as `int line = Position.current().line`. A default that computes from it, as `int next = Position.current().line + 1`, is a compile error.

**A `.sharp` file runs only after the checker accepts it.** A type error stops it from running. A comment is text in every file Mago reads, `.sharp` and `.php` alike, so no comment hides an issue. A project turns a rule off in its `mago.toml`. `mago.toml`'s `ignore` or the baseline can hide a warning, never one of the language's errors. They can hide an error of an extension host's rule, whose code names its host, as `acme/no-raw-sql`.

<!-- example a-comment-hides-no-error: members -->
```csharp
// @mago-expect analysis:invalid-return-statement
public int total() => "one";                                    // compile error: a comment hides no error
```

**`vendor/bin/mago compile` compiles the project.** It checks every `.sharp` file, vendor packages included, and proves every law they state (section 28).

- **A `.sharp` file that isn't compiled, or is out of date, is refused,** and the refusal names the fix:

```text
app/Orders/Order.sharp isn't compiled. Run vendor/bin/mago compile.
app/Orders/Order.sharp is out of date (app/Shared/Money.sharp changed). Run vendor/bin/mago compile.
app/Orders/Order.sharp was compiled for a different PHP# engine. Install the mago-sharp release that matches this engine.
```

- **An edit that keeps a file's declarations keeps every file that reads it current.** A compiled file names the hash of its file's declarations: each type's and member's name and signature, a method's without its body. A file that reads another `.sharp` file of the project, through its declarations as `typeof(Money)` does or by calling its methods as `money.amount()` does, records that hash beside the other file's stamp. After an edit of a method body, the engine runs it while the other file's compiled file names the hash it recorded, reading that compiled file's header and never the edited source, and the next compile leaves its compiled file as it is, unless a return or an effect it took from the edited body changed. The edited file itself is out of date until it compiles. A file keeps the stamp of each plain PHP file and each file in `vendor/` it reads, of each source its laws reach, and of a `.sharp` file that states an `extern` line or declares an event or a struct, so any change to one makes it out of date.
- **A deploy** runs `vendor/bin/mago compile` before the code runs.
- **A compile with nothing to do returns at once.** A compile keeps `.sharp/inputs.json`, a record of everything it read and wrote and of what it printed: each `.php` and `.sharp` file under the configured paths, includes and patches with its real path, but for the folders the configuration excludes and the plain PHP files of the vendor folder, which `composer.lock`, Composer's `installed.json` and its autoload maps stand for, every `composer.json` the loader reads, Composer's `installed.json` and its autoload maps in `vendor/composer`, the baseline, the configuration, the checker build, and each file it wrote. The next compile checks the record before it analyzes anything, and when nothing in it changed, it prints the same outcome, a refused file's errors included, exits as the compile did, and stops. A file whose size, times, inode or device changed is hashed. On a filesystem whose change time records every write, as APFS, ext4 and most others do, that sees a rewrite that keeps the size and sets the time back. On FAT and exFAT, and on a system other than macOS and Linux, the compile hashes every file. A compile that warns keeps no record, so the next one prints the same warnings, and so does one whose law checker refused a file, so the next one runs the checker again. A project with an extension host enabled compiles every time, because a host doesn't say what it reads. The compile for the hosts keeps the same record in its own folder, whatever it logs, since the run that follows it reports what the project's files hold.
- **A compile lowers again only the files an edit changes.** A `.sharp` file keeps its compiled file while its key holds: its source, and the declarations, inferred returns, sets of names, library forms and effects its check read. `.sharp/compiled.bin` keeps what each file read, so the next compile checks that without lowering it, and is set aside once the configuration or the checker changes. A law is proved once while its key holds: its statement and proof, those of each law it calls, and the text of every declaration they reach. `.sharp/proofs.json` keeps how each proof ended, proven or not, so an edit no law reaches proves nothing again, and an unproven law is reported again without Lean.
- **In development, the engine compiles a file that isn't current when it loads,** so a request always runs the code on disk. The engine's `sharp.compile_command` setting names the command, in a dev `php.ini`:

```text
sharp.compile_command = "vendor/bin/mago --no-version-check --no-extensions compile"
```

- **A request runs the command at most once,** in the project root, waits for it, and loads what it wrote. The root is the nearest folder above the file that holds `.sharp/`, so a project's first compile is by hand.
- **A project compiles once at a time.** A request holds the lock of `.sharp/compile.lock` while it runs the command. A request that finds a file stale while another compiles waits for it, checks its file again, and runs the command only when the file is still stale, so after a refused compile it gets the refusal from the record.
- **Runs of `mago` that write `.sharp/` take turns.** Each holds the lock of `.sharp/write.lock` from before it reads the folder's record to after it deletes what it did not write, so a `mago compile` and a `mago analyze` started together on one tree each pass, and the second reads the record the first wrote. The command a request runs takes this lock while the request holds `.sharp/compile.lock`.
- **The command ends with the request.** It runs in a process group of its own, and each process of the group still running once the command exits is killed, as is the whole group when the request ends first.
- **`--no-extensions` leaves the extension hosts' rules out of the compile a request waits for.** They run in `mago analyze` and in CI.

- **`mago analyze` of the whole project compiles it before it analyzes with the hosts.** It writes the `.sharp` folder as `mago --no-extensions compile` writes it, laws proven, and the hosts' folder, from one analysis without the hosts and one lowering, then analyzes with the hosts and reports each issue of both once. So a save needs `mago analyze` alone. A `mago analyze` of named files, of staged files or of standard input, or with `--scan-only`, compiles only the hosts' folder.
- **A compile never starts another.** The command runs with `SHARP_COMPILING=1`, and so does every process `mago` starts. A process that has it set never runs the command.
- **A command that fails adds how it ended to the refusal,** with its last lines of stderr:

```text
app/Orders/Order.sharp is out of date (app/Shared/Database/BaseModel.php changed). Run vendor/bin/mago compile. `vendor/bin/no-such-mago compile` failed with exit 127: /bin/sh: 1: vendor/bin/no-such-mago: not found
```

- **Production leaves the setting empty,** as the runtime images do. A stale deploy is then refused, and no request waits for a compile.
- **`mago compile` writes `.sharp/preload.php`, which preloads every type it compiled.** A production `php.ini` names it in `opcache.preload`. The file requires the project's Composer autoloader, then each accepted `.sharp` file that declares a type, the packages' in `vendor/` included, so a type whose parent another file declares, plain PHP or PHP#, gets it from the autoloader, as in a request. PHP declares an anonymous class only when its code runs, so the file then loads each type, PHP's own left out, that an anonymous class in a file the preload loads names as its parent, an interface or a trait. Such a file is one Composer's autoloader loads up front, as `illuminate/reflection`'s `helpers.php`, or the file of a type a preloaded type extends. Opcache would warn otherwise that it can't preload the class. A file in an `autoload-dev` folder stays out, so production never preloads a test. The compile rewrites it on every compile:

```text
opcache.preload = /srv/app/.sharp/preload.php
```

- **A type error stops the file from running, an import its module's `imports` line leaves out included.** So does a law the checker cannot prove, a compile error that names the law and what is still to show.
- **A plain PHP class that doesn't satisfy a PHP# type it extends or implements stops `mago compile`** (section 22), and `mago compile` still writes every accepted `.sharp` file.
- **`mago compile` hides exactly what `mago analyze` hides:** the same `ignore` entries and baseline hide a host rule's error from both, so the two commands report the same errors.
- **An extension host loads the project's own classes, on a fresh or read-only tree and after any change.** Before a command starts its hosts, `mago compile`, `mago analyze` (`--watch` too) and `mago lint` compile the project for the hosts into a folder of mago's cache, outside the project, which only the hosts' engine reads, so the project's `.sharp` folder holds only what `mago compile` accepted. That compile lowers every file that lowers, whatever the checker says of it, so a host still loads the project while it has type errors: a host that loads a file with a type error gets the error the code raises when it runs, and a host that loads a file that doesn't lower gets the checker's errors. PHP declares a class once, so a watch starts a host's worker again when the compiled file of a class the worker loaded, or asked for and never declared, changes, and checks again each file whose hooks or providers ran in that worker after it loaded the class. Any other edit is checked as an edit without hosts is, by the workers already running.
- **A rule that reads the whole project's structure can lag in development** until the next full compile. At deploy, `mago compile` checks every rule exactly.

## 28. Verification

The checker verifies two kinds of facts before code runs:

- **Structure,** meaning what a namespace may import, is a module's `imports` line (section 32), which the checker checks as it checks types.
- **Values:** facts the code guarantees, written as laws.

**A class states its laws in PHP#,** with `law`. A law is a claim over every value of its parameters, `law name(parameters) => claim;`, and the checker proves it alone. A law the checker cannot prove alone carries a body after its claim, in place of the `;`, and the body is its proof, in ordinary PHP#. Laws and their proofs live in the `.sharp` file, beside the code they state facts about. The checker turns each law into a Lean theorem in `.sharp/`, and Lean's kernel checks every proof. Nobody writes or reads Lean.

<!-- example class-law -->
```csharp
// app/Shared/Money.sharp
namespace App.Shared;

public class Money
{
    public Money(public int amount { get; }, public string currency { get; }) { }
    public Money add(Money other) => new Money(this.amount + other.amount, this.currency);
    public Money times(int n) => n <= 0 ? new Money(0, this.currency) : this.add(this.times(n - 1));

    law addKeepsCurrency(Money a, Money b) => a.add(b).currency == a.currency;   // compiles: the checker proves it alone
    law timesKeepsCurrency(Money m, int n) => m.times(n).currency == m.currency   // compiles: its body proves it
    {
        if (n <= 0) {
            return;                                     // m.times(n) is new Money(0, m.currency)
        }
        Money.timesKeepsCurrency(m, n - 1);             // the law for the smaller n
        Money.addKeepsCurrency(m, m.times(n - 1));      // adding keeps that currency
    }
}
```

**A law's body is ordinary PHP#.** It holds `if` and `else`, `match` statements whose arms are blocks, locals, `return;`, and calls of laws as statements, and nothing else. It has no effects.

- **Each `if` and each `match` arm is a case** that knows its condition, or its pattern and its `when` guard. An arm that joins patterns with `or` is a case for each of them. A `match` in a body takes every arm a `match` takes, of a parameter of the law, a value a pattern of the body names, or a property of one.
- **`return;` closes the case it ends.** The checker proves the claim there with what the case knows. So does the end of the body.
- **A call of a law is a fact the case knows from then on:** the claim of that law for the call's arguments. Its arguments are any pure expressions.
- **A law is reached through its class's name,** as a static method is, as `Money.addKeepsCurrency(m, x)`. A module's law is reached as `Module.name(…)` (section 32).
- **A law is called only as a statement of a law's body.** A law never runs, so a call of it anywhere else is a compile error.
- **Every proven law is a fact later laws may call,** and the checker's own steps use the laws a body calls.

<!-- example law-calls -->
```csharp
// app/Shared/Money.sharp
namespace App.Shared;

public class Money
{
    public Money(public int amount { get; }, public string currency { get; }) { }
    public Money add(Money other) => new Money(this.amount + other.amount, this.currency);
    public static bool same(Money a, Money b) => Money.addKeepsCurrency(a, b);   // compile error: `Money.addKeepsCurrency` is a law, and only a law's body calls a law, as a statement of its own

    law addKeepsCurrency(Money a, Money b) => a.add(b).currency == a.currency;
    law addTwiceKeepsCurrency(Money a, Money b) => a.add(b).add(b).currency == a.currency
    {
        Money.addKeepsCurrency(a, b);
        Money.addKeepsCurrency(a.add(b), b);
    }
}
```

**A law calls itself only on a smaller value:** a list's rest from a list pattern the body matched, or an int one step towards a bound the body tested, as a method's recursion does (below). Any other call of itself, and two laws that call each other in a cycle, is the compile error "a law calls itself only on a smaller value".

<!-- example law-recursion -->
```csharp
// app/Shared/Money.sharp
namespace App.Shared;

public class Money
{
    public Money(public int amount { get; }, public string currency { get; }) { }
    public Money add(Money other) => new Money(this.amount + other.amount, this.currency);

    public static Money sum(Money total, List<Money> items) => match (items) {
        [] => total,
        [Money first, ...List<Money> rest] => Money.sum(total.add(first), rest),
    };

    public static int total(List<Money> items) => match (items) {
        [] => 0,
        [Money first, ...List<Money> rest] => first.amount + Money.total(rest),
    };

    law addKeepsCurrency(Money a, Money b) => a.add(b).currency == a.currency;
    law sumKeepsCurrency(Money total, List<Money> items) => Money.sum(total, items).currency == total.currency
    {
        match (items) {
            [] => { return; },
            [Money first, ...List<Money> rest] => {
                Money.sumKeepsCurrency(total.add(first), rest);   // the law for the list's rest
                Money.addKeepsCurrency(total, first);
            },
        }
    }
    law totalJoins(List<Money> a, List<Money> b) => Money.total([...a, ...b]) == Money.total(a) + Money.total(b)
    {
        match (a) {
            [] => { return; },
            [Money, ...List<Money> rest] => { Money.totalJoins(rest, b); },   // the law for a's rest
        }
    }
}
```

<!-- example law-smaller-value -->
```csharp
// app/Shared/Money.sharp
namespace App.Shared;

public class Money
{
    law broken(int x) => x == x + 1 { Money.broken(x); }   // compile error: a law calls itself only on a smaller value
}
```

**`when` after a law's parameters states its condition:** the claim holds for every value that meets it. A law with `when` is called only where its condition holds, and a call where the case does not know it is an error that names the condition still to show.

<!-- example law-when -->
```csharp
// app/Shared/Refunds.sharp
namespace App.Shared;

public class Refunds
{
    public static int refundable(int paid, int refunded) => Math.max(paid - refunded, 0);

    law neverAbovePaid(int paid, int refunded) when paid >= 0 && refunded >= 0
        => Refunds.refundable(paid, refunded) <= paid;
    law refundOfNothing(int paid) when paid >= 0 => Refunds.refundable(paid, 0) == paid;
    law refundOfAnything(int p, int r) => p < 0 || Refunds.refundable(p, r) <= p
    {
        Refunds.neverAbovePaid(p, r);                     // compile error: still to show for the call Refunds.neverAbovePaid(p, r): p >= 0 && r >= 0
    }
}
```

**A law's claim and its `when` condition compute ints exactly,** since a law never runs. `Money.total(a) + Money.total(b)` in a claim never overflows, so a sum law holds for a list's rest wherever the code it calls runs. The code a law calls throws `ArithmeticError` where it overflows at runtime (section 24), and a law holds wherever its code throws.

**A case the checker cannot close is the error `law-unproven`** on the law. It names the case's conditions and the claim still to show, in PHP#, and a `help` line names a call of the law itself on a smaller value when the case makes none:

<!-- example law-unproven -->
```csharp
// app/Shared/Money.sharp
namespace App.Shared;

public class Money
{
    public Money(public int amount { get; }, public string currency { get; }) { }
    public Money add(Money other) => new Money(this.amount + other.amount, this.currency);
    public Money times(int n) => n <= 0 ? new Money(0, this.currency) : this.add(this.times(n - 1));

    law timesIsZero(Money m, int n) => m.times(n).amount == 0   // compile error: law timesIsZero is not proven
    {
        if (n <= 0) {
            return;
        }
    }
}
```

```text
error[law-unproven]: law timesIsZero is not proven
  when n > 0, still to show: m.add(m.times(n - 1)).amount == 0
  help: the law for a smaller n is the call Money.timesIsZero(m, n - 1)
```

**A module states laws that span its classes** in `Module.sharp` (section 32), with bodies as a class's laws have.

<!-- example module-law -->
```csharp
// app/Tenant/Store/Module.sharp
namespace App.Tenant.Store;

module
{
    law receiptMatchesRefunds(int paid, int refunded) => Receipts.refundable(paid, refunded) == Refunds.remaining(paid, refunded);
    law receiptNeverNegative(int paid, int refunded) when refunded <= paid => Refunds.remaining(paid, refunded) >= 0
    {
        Module.receiptMatchesRefunds(paid, refunded);
    }
}
```

A law the checker cannot prove is a compile error that names it (section 27).

**Laws hold only over pure code** (section 29). The checker translates pure PHP# code to Lean, and Lean's kernel checks the proofs. A law reaches the code it calls, and that code's own calls, and code no law reaches is never translated or refused:

- **A method may call itself, or a method of its class that calls it back, only on a smaller value:** a list's rest from a list pattern, or `n - 1` or `n + 1` towards a bound an `if`, `?:`, `&&`, `||` or `match` comparison tested. The bound is an int literal, tested with `<`, `<=`, `>` or `>=`, or another int parameter the call passes on unchanged, tested with `<` or `>`.
- **A `for (… of …)` loop over a List, or over a Map's entries,** reaches a law when its body only adds pure int values to int locals with `+=` or `++`, each under an `if` or not, and never reads a local it adds to.
- **A list pattern takes one rest, anywhere in it,** and each element names a value or only a type. `[...a, x, ...b]` appends the lists.
- **A law reaches the standard library from its PHP# source, as it reaches the project's code,** so a type such as `Instant` translates from its declaration and each member a law calls, and a member that calls plain PHP is refused where the law reaches it, as the project's would be. `Math.max` and `Math.min` on ints, a List's `count()`, `map` and `filter`, and a Map's literal, `count()`, `isEmpty()`, `isNotEmpty()`, `get`, `has`, `keys()`, `values()`, index read and loop over its entries, keep the checker's own model of what they give, and so does `(string)` of an int, its decimal digits. `Instant.toString()` writes its UTC text in PHP# with no plain PHP, so a law reaches the text it gives. A law that reaches another member of a Map is refused, naming those it reads.
- **`<`, `<=`, `>`, `>=` and `<=>` on two values of one class run the class's own `operator <=>`,** so a law that orders two `Instant`s proves through `Instant`'s.
- **`==` and `!=` on two values of a struct that declares no `operator ==` compare each field with that field's own `==`, and on two values of a class that declares `operator ==` run it,** so a law that compares two `Instant`s proves. On two Lists they compare as many elements on each side, and each pair by the element's own `==`, so `marks == marks` on a `List<Mark>` proves, and so does `==` on two structs with a `List<Mark>` field. With a nullable side they compare as section 19 lifts them: null equals only null, and two values run the class's `==`, so `stamp.at == at` on an `Instant?` and an `Instant` proves, and so does `==` on two structs with an `Instant?` field.
- **A value of a class holds the fields of every PHP# class above it,** so a law reads a field, calls a method and runs `new` through `: super(…)` with what a PHP# parent declares, at any depth. The first plain PHP class above it, and each class above that, is plain PHP: a law carries the value and takes it as a parameter, and a read of a member such a class declares, or a `new` that runs its constructor, is refused where the law reaches it.
- **A constructor whose body only checks its arguments and throws, as `Instant`'s `if (nanosecond < 0 || nanosecond > 999_999_999) { throw new ValueError(…); }` does, reaches a law, and its checks are a precondition of the value,** so `new Instant(s, n).nanosecond <= 999_999_999` proves, as a throw breaks no law. A constructor body that does anything else is refused where the law reaches it.
- **Each refusal names what to write instead,** as these do:

```text
Law summed reaches a loop in Shape.sum. Write the method as recursion that calls itself on a smaller value.
Law joinedText reaches a loop that does more than count or sum in Shape.joined. Write the method as recursion that calls itself on a smaller value.
Law cappedHolds reaches a loop that reads a local it adds to in Shape.capped. Write the method as recursion that calls itself on a smaller value.
Law reachesZero reaches a recursion that does not get smaller in Shape.up. Write the method as recursion that calls itself on a smaller value: a list's rest from a list pattern, or an int one step towards a bound a condition tested.
Law nullIsSmall reaches a pattern that names a value outside the condition of an if or a ?: in Shape.big. Write the test as the condition of an if or a ?:, and read the value it names in the branch it decides.
Law shapeIsShape reaches a type test of the interface Figure in Shape.isShape. A law tells apart only the implementers of a sealed interface. Declare `Figure` sealed.
Law readsBase reaches the plain PHP property Base.x in Shape.readsBase. Write the code the law reaches in PHP#, or pass the value plain PHP computes to the law as a parameter.
```

<!-- example law-reach -->
```csharp
// app/Shared/Steps.sharp
namespace App.Shared;

public class Steps
{
    public static int walk(int n)
    {
        if (n <= 0) {
            return 0;
        }
        return 1 + Steps.walk(n - 1);                   // n - 1 towards the bound 0 the if tested
    }

    public static int climb(int i, int top) => i >= top ? 0 : 1 + Steps.climb(i + 1, top);

    public static int size(List<int> items)
    {
        let count = 0;
        for (const item of items) {
            count++;
        }
        return count;
    }

    law twoSteps(int unused) => Steps.walk(2) == 2;
    law climbsTwo(int unused) => Steps.climb(0, 2) == 2;
    law sizeIsCount(List<int> items) => Steps.size(items) == items.count();
}
```

**A state machine is a `machine`** (section 20.2), and its invariants are its laws: the checker proves each one after every entry point and every move that gives its case, and a failed proof prints the moves that break it:

<!-- example machine-invariant -->
```csharp
// app/Refund.sharp
public machine Refund
{
    case Open
    {
        public Refund.Paid pay(int cents) => Refund.Paid(cents);
    }
    final case Paid(int cents);

    public static Refund.Open open() => Refund.Open;

    law paidIsPositive(Refund.Paid paid) => paid.cents > 0;        // compile error: paidIsPositive breaks after open() → Open, pay(cents: 0) → Paid(cents: 0)
}
```

**A state that holds data is a sealed interface's implementer, or an enum's data case,** and a law tells the states apart. A sealed interface's implementers (section 20.1) are every value it has, so `is` and `match` on its value test which implementer it is, and the branch reads that implementer's get-only properties. A data case's pattern reads its fields, and so does a property read of a value the pattern names. An interface that is not sealed has implementers a law cannot list, so a law that reaches a type test of its value is refused.

### 28.1 Lean files

A law and its proof sit in the `.sharp` file that states it.

```text
app/
├── Shared/
│   └── Money.sharp              <- states and proves the laws addKeepsCurrency and timesKeepsCurrency
└── Tenant/Store/
    ├── Module.sharp             <- the module (section 32), its imports line, and the law receiptMatchesRefunds
    ├── Receipts.sharp
    ├── Refunds.sharp
    └── StoreService.sharp
```

- **The checker generates the Lean translation of pure code and the theorem of each law into `.sharp/`,** never into the source tree, on each compile. No Lean file of the project proves a law.
- **A law's generated statement is named by its class's full name and the law's name,** as in `App.Shared.Money.addKeepsCurrency`. A module law's is named by its namespace, `Module` and the law's name, as in `App.Tenant.Store.Module.receiptMatchesRefunds`.
- **A law the checker cannot prove is a compile error that names it,** with what is still to show.
- **Names match exactly.** Translated code keeps its PHP# names in Lean, with no prefix.
- **The checker names the law in each error about it,** as in `law addKeepsCurrency is not proven`.

## 29. Effects

An effect is anything a method does beyond computing its result: database, network, files, clock, randomness, mail. PHP# tracks effects through the objects a class holds, a model called object capabilities. It also records every call into plain PHP, whose effects its `extern` line states, and every `emit`, which raises an event and has the effect `Events` (section 15).

**Any PHP# code may call plain PHP that an `extern` line declares,** such as a library's classes, facades and models. Most libraries are plain PHP, so this is how PHP# code uses them. PHP's own built-in functions are the exception: the standard library replaces them (section 8).

<!-- example plain-php-calls -->
```csharp
import Illuminate.Support.Facades.DB;

public class OrderReport
{
    public int openCount() => DB.table("orders").where("status", "open").count();
}
```

**The effects of plain PHP are declared once, with `extern`.** A `.sharp` declaration file names a plain PHP class, one of its methods or a function, and its effects after `uses`. A declaration holds no code.

<!-- example extern-declaration -->
```csharp
// app/Stubs/Stripe.sharp
namespace App.Stubs;

import Stripe.StripeClient;

extern StripeClient uses Http;
```

- **A declaration file is an ordinary `.sharp` file.** Its `namespace` line matches its path, as section 5 requires.
- **`extern` covers a whole class, one method or a function,** each after importing it: `extern StripeClient uses Http;`, `extern Carbon.now uses Clock;`, `extern Pdf.render uses Files;` for a function after `import Vendor.Pdf;`, or `extern Foundation.now uses Clock;` for a function a library declares outside any namespace, after `import Illuminate.Foundation;` and the `extern namespace` line that gives its folder that namespace (section 23). `extern namespace WordPress for WP_Post;` declares no effect: it gives a library's folder of root names the namespace its functions are declared through, as `extern WordPress.get_post uses Database;` (section 23).
- **Every `extern` line ends in `uses`,** which states its effects on purpose. `uses none` declares the class, method or function pure, as in `extern BigDecimal uses none;`, a list names its effects, and `uses any` declares that it may have any effect, as `extern Container.make uses any;` does: code that reaches it is never pure, never takes part in a law (section 28), and fits no `uses`. A line without `uses` is a compile error, "State its effects: `uses none`, `uses <effects>`, or `uses any`.", so a line written only for its signature never claims its target pure. `uses none` and `uses any` stand alone, so `uses any, Http` is a compile error. An `extern namespace` line states no effects (section 23).
- **An `extern` of a method or a function may state its signature,** as `extern string Routing.route(RouteName name) uses Http;` does, and its `uses` follows the signature (section 23). A function value its `callable` parameter takes (section 7) adds its own effects to the call, as any function plain PHP is given does.

<!-- example extern-functions -->
```php
namespace Vendor\Pdf;

function render(string $html): string { return $html; }
```

<!-- example extern-functions -->
```csharp
// app/Stubs/Functions.sharp
namespace App.Stubs;

import Illuminate.Foundation;
import Vendor.Pdf;

extern namespace Illuminate.Foundation for now;
extern Pdf.render uses Files;        // compiles: Pdf.render(html) has the effect Files
extern Foundation.now uses Clock;    // compiles: Foundation.now() has the effect Clock
extern now uses Clock;               // compile error: no function now exists
```

- **Each class, method or function has at most one `extern` declaration in the whole project.** A second one is a compile error, as declaring a class twice is.
- **Every plain PHP class, interface, trait and function PHP# code reaches has an `extern` line.** A call, a `new`, a static call or a property read of plain PHP that no line declares is a compile error that names the line to write: "`Mailchimp` has no `extern` line.", with `extern Mailchimp uses any;` as the line. PHP's built-ins the standard library declares are declared already.
- **One line for a class covers its members, and a method's own line overrides its class's line.** After `extern CarbonImmutable uses none;` and `extern CarbonImmutable.now uses Clock;`, `CarbonImmutable.now()` has the effect `Clock` and `date.addDays(1)` is pure.
- **A call has the effects of the line of the class, trait or interface that declares the method it calls,** and nothing passes from the class a call goes through or from the call that returned the object. A facade's docblock `@method` declares the method on the facade, so `DB.table(…)` takes `DB`'s line. `new` takes the line of the class it creates, as `new ValueError("empty")` takes `ValueError`'s, or of the nearest plain PHP class a PHP# class extends, and a read of a property takes the line of the class that declares it. `StripeClient.charges().create(…)` has the effects of `StripeClient`'s line for `charges()`, and of the line of the class `charges()` returns for `create(…)`, which a project declares on its own, so `Order.all().sum("total")` has only `Database` from `all()` after `extern Collection uses none;`.
- **A plain PHP trait's member is one body, so its `extern` line covers every class that takes the member from the trait.** After `extern Dispatchable.dispatch uses Database;`, `ReceiveEvent.dispatch(…)` has the effect `Database` where `ReceiveEvent` lists `Dispatchable` in its header. A class that writes `dispatch` itself runs its own body, which the line does not cover.
- **Reflection has the effects of the code it runs** (section 25). `Property.get` and `Property.set` run an accessor no `uses` can name, so each may have every effect. `Parameter.defaultValue()` reads only a default that runs no code and refuses any other, so it is pure. `Property.defaultValue()` and `Field.defaultValue()` run the initial value they read, so they have the effects of each one the program declares that is not constant. A constant initial value, as `null`, `0`, `[]` or an enum case, runs no code, so in a program whose initial values are all constant a method held to `uses Database` reads one. `Method.createDelegate` and `Method.createInvoker` are pure, and a call of the value each gives has the effects its function type lists, which the method's own must fit. `Field.get`, `Field.set` and every `isInitialized` run no code a project wrote, so each is pure. `attributes<T>()` creates attributes, so it is pure when `T` is a PHP# class that no plain PHP class extends, whose constructor and initial values have no effects (section 1), and may have every effect for a plain PHP `T` or for `Object`.
- **A project declares the effects of every library it uses,** conventionally in `app/Stubs`. PHP# ships declarations for no framework, so every library is declared the same way. `mago convert` writes `extern X uses any;` there for each plain PHP class a converted file reaches that has no line, so the converted file compiles, and the project narrows each `uses any` to the effects it knows.
- **An `extern` line takes any attribute, which the class, method or function it declares carries as its own,** as every declaration takes one (section 1). The checker reads it as the declaration's, so a `[DynamicallyAccessed]` written on a class's line marks a library's attribute class whose source carries no mark (section 5). At run time PHP#'s `typeof(X).attributes<T>()` and `Method.attributes<T>()`, and plain PHP's `ReflectionClass`, `ReflectionMethod` and `ReflectionFunction` `getAttributes()`, give the declaration's own attributes and then the line's, though no code loads the file that holds the line. A method takes them on the line of the class that declares it, so an attributed line of a method the class only inherits is a compile error that names that class. An `extern namespace` line (section 23) declares nothing, so an attribute on it is a compile error. `import` and `extern` stay separate lines.

<!-- example extern-attributes -->
```csharp
// app/Stubs/Symfony.sharp
namespace App.Stubs;

import Symfony.Component.EventDispatcher.Attribute.AsEventListener;

[DynamicallyAccessed]
extern AsEventListener uses none;   // compiles: AsEventListener carries [DynamicallyAccessed], so a method marked
                                    // [AsEventListener] is never too wide
```
- **PHP's own built-in functions and exception classes come declared.** PHP#'s Composer package declares the effect of each built-in function the standard library calls, so `Files` has the effect `Files`. Each built-in exception class is declared `uses none`, so pure code may `throw new ValueError("empty")`.

**The standard library's twelve effect classes replace PHP's built-ins for their effects.** Each lives in the namespace of its domain (section 23), and a `uses` names it by its class name with no import. A name in `uses` resolves as any class name does, so a class the file imports or declares under the name, or one in the file's own namespace, wins over the effect, as `App.Math` wins over `Math`, and a file that imports a facade named `Http` imports the effect under another name, `import Sharp.Net.Http as HttpEffect;`:

- `Database`, in `Sharp.Data`, replaces PDO.
- `Http`, in `Sharp.Net`, replaces curl.
- `Files`, in `Sharp.IO`, replaces every file function outside standard output and standard error.
- `Console`, in `Sharp.IO`, replaces printing to standard output and standard error.
- `Process`, in `Sharp.System`, replaces `exit` and `shell_exec()`.
- `Clock`, in `Sharp.Time`, replaces `time()`, and its `now()` gives an `Instant`.
- `Random`, in `Sharp`, replaces `random_int`.
- `Cache`, in `Sharp.Data`, replaces the APCu functions.
- `Mail`, in `Sharp.Net`, replaces `mail()`.
- `Dns`, in `Sharp.Net`, replaces `dns_get_record()` and `gethostbyname()`.
- `Environment`, in `Sharp.System`, replaces `getenv()` and PHP's other environment functions.
- `PhpGlobals`, in `Sharp.Interop`, replaces `global`, `$GLOBALS` and the superglobals, and runs a plain PHP file in the global scope.

A bare call of a replaced function names no function (section 8), and a PHP built-in class names no class (section 23), so only the standard library reaches them. Inside it, creating a built-in class has the effects its `extern` lists, as creating a `DateTimeImmutable` reads the clock.

**`Dns.addresses(host)` gives every IPv4 and IPv6 address of a host,** each once, as an `IpAddress`, in the order the system resolver gives them. The system resolver reads the hosts file before it asks a DNS server, so `localhost` resolves with no network. A name that is no host name throws `ValueError` before any lookup, and a host the resolver can't find throws `RuntimeException`:

<!-- example dns -->
```csharp
// app/WebhookTarget.sharp
namespace App;

import Sharp.Net.Dns;

public class WebhookTarget
{
    public WebhookTarget(private Dns dns) { }

    public bool isPublic(string host) => this.dns.addresses(host).all(address => !address.isLoopback() && !address.isPrivate());   // compiles: WebhookTarget holds a Dns, so it has the effect Dns
}
```

<!-- example dns -->
```csharp
// app/Module.sharp
namespace App;

module
{
    transient WebhookTarget = WebhookTarget;   // the standard library's module gives it the app's Dns
}
```
<!-- run dns
public ExampleRun(private WebhookTarget target) { }
this.target.isPublic("localhost");          // false
this.target.isPublic("nothing.invalid");    // throws RuntimeException
this.target.isPublic("not a host");         // throws ValueError
-->

**`IpAddress`, in `Sharp.Net`, is an IPv4 or IPv6 address.** `IpAddress.parse(text)` and `IpAddress.tryParse(text)` read either family, and `family` gives `IpFamily.V4` or `IpFamily.V6`. `isLoopback()` is true for `127.0.0.0/8` and `::1`, and `isPrivate()` for RFC 1918's `10.0.0.0/8`, `172.16.0.0/12` and `192.168.0.0/16` and IPv6's unique local `fc00::/7`. `toString()` writes RFC 5952's form, and two addresses are `==` when they are one address, however each was written:

<!-- example ip-address: statements
import Sharp.Net.IpAddress;
import Sharp.Net.IpFamily;
-->
```csharp
IpAddress.parse("0:0:0:0:0:0:0:1") == IpAddress.parse("::1");   // true
IpAddress.parse("2001:DB8::1").toString();                     // "2001:db8::1"
IpAddress.parse("10.0.0.7").isPrivate();                       // true
IpAddress.parse("127.0.0.1").family == IpFamily.V4;            // true
IpAddress.tryParse("example.com");                             // null
```

**`Process.start(program, arguments)` starts a program and returns while it runs,** where `Process.run` waits for it. The `ChildProcess` it gives writes the program's input with `writeInput(text)` and closes it with `closeInput()`. `readOutput()` and `readError()` give everything the program writes to its output and to its error output, once it ends. `wait()` closes the input, waits for the program to end and gives its exit code, and `kill()` ends the program at once. The output goes to temporary files, so a program that writes a lot never waits for a reader. A `ChildProcess` runs through the `Process` that started it, so code that holds one has the effect `Process`:

<!-- example process-start -->
```csharp
// app/Echo.sharp
namespace App;

import Sharp.System.Process;

public class Echo
{
    public Echo(private Process process) { }

    public string back(string line)
    {
        const cat = this.process.start("cat");
        cat.writeInput(line);
        cat.closeInput();

        return cat.readOutput();   // compiles: Echo holds a Process, so it has the effect Process
    }
}
```

<!-- example process-start -->
```csharp
// app/Module.sharp
namespace App;

module
{
    transient Echo = Echo;   // the standard library's module gives it the app's Process
}
```
<!-- run process-start
public ExampleRun(private Echo echo) { }
this.echo.back("one line");   // "one line"
-->

**`ZipArchive`, in `Sharp.IO`, reads and writes a ZIP archive.** `ZipArchive.open(path)` opens one to read and to add to, and `ZipArchive.create(path)` makes a new one in place of any file at `path`. `list()` gives each entry's name in the archive's order, and `read(name)` gives an entry's text, or null when the archive has none. `write(name, text)` adds an entry from text and `addFile(file, name)` one from a file, each in place of an entry of that name, and `extractTo(directory)` writes every entry into a folder. The changes reach the file when `close()` runs, and an archive with no entries is written too. Each member reads or writes files, so code that calls one has the effect `Files`. A failure throws `RuntimeException` with the path and libzip's reason, as `ZipArchive.open: "report.csv" cannot be opened: Not a zip archive`:

<!-- example zip-archive -->
```csharp
// app/Export.sharp
namespace App;

import Sharp.IO.ZipArchive;

public class Export
{
    public void pack(string path, string report)
    {
        const archive = ZipArchive.create(path);
        archive.write("readme.txt", "Totals by month");
        archive.addFile(report, "data/report.csv");
        archive.close();                                                  // compiles: Export has the effect Files
    }

    public string? readme(string path) => ZipArchive.open(path).read("readme.txt");
}
```

<!-- example pure-interface -->
```csharp
// app/Formatter.sharp
namespace App;

public interface Formatter
{
    string format(string name);                                     // implementations must be pure
}
```

<!-- example pure-interface -->
```csharp
// app/Visitor.sharp
namespace App;

import Sharp.IO.Files;

public class Visitor
{
    public Visitor(private Files files) { }

    public string greet(string name) => "Hello, " + name.trim();    // pure: trim is a standard-library method
    public void remember(string token) { this.files.write("seen.txt", token); }   // has Files
}
```

<!-- example pure-interface -->
```csharp
// app/FileFormatter.sharp
namespace App;

import Sharp.IO.Files;

public class FileFormatter : Formatter
{
    public FileFormatter(private Files files) { }

    public string format(string name)
    {
        this.files.write("seen.txt", name);                         // compile error: format must be pure, and files.write has the effect Files
        return name;
    }
}
```

**A `foreign` class turns an effect into an object** that code holds and passes on, such as a fake in tests. It is optional. Its methods call plain PHP, like any other code. Code that holds a `foreign` object has that effect by name, which `uses` can declare.

<!-- example foreign-class -->
```csharp
import Illuminate.Support.Facades.Redis;

public foreign class RedisStore
{
    public string? get(string key) => Redis.get(key);
}
```

The standard library's twelve effect classes, listed above, are `foreign` classes. A project declares its own `foreign` classes the same way.

**`Events` is the effect of `emit`** (section 15). It is the one standard effect that is not a `foreign` class: a method has it when its body emits, and no object carries it. A listener runs inside the `emit` that raised its event, under the standard library's dispatcher, so an exception it throws leaves that `emit`, and the listeners after it don't run, as with Symfony's dispatcher. Code that must keep going catches it around the `emit`.

**PHP# has no superglobals.** `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`, `$_REQUEST`, `$_SESSION`, `$_ENV` and `$GLOBALS`, with or without `$`, fail as a typo does. Request data arrives as a parameter, such as a framework's `Request`. The process environment and the command-line arguments arrive as `Environment`, a standard `foreign` class like `Clock` and `Random`:

- `string? variable(string name)` reads an environment variable, or gives null when it is not set.
- `Map<string, string> variables()` reads every environment variable, by name, on every call.
- `List<string> arguments { get; }` holds the command-line arguments, starting with the script's name.
- `string currentDirectory()` reads the directory the process runs in on every call.
- `void changeDirectory(string path)` changes the directory the process runs in.
- `string require(string name)` reads an environment variable, and throws `ValueError` when it is not set, with the message `Environment.require: the environment variable "NAME" is not set`. A module binding calls it on the `Environment` it takes as a parameter (section 32).
- `int processorCount()` gives how many processors the machine has online, at least 1, and `string machineName()` gives its host name.

<!-- example environment -->
```csharp
// app/Deploy.sharp
namespace App;

import Sharp.System.Environment;

public class Deploy
{
    public Deploy(private Environment environment) { }

    public string region() => this.environment.variable("AWS_REGION") ?? "us-east-1";
    public string target() => this.environment.arguments[1];   // throws OutOfRangeException when no argument was passed
}
```

<!-- example environment -->
```csharp
// app/CheckoutController.sharp
namespace App;

import Illuminate.Http.Request;

public class CheckoutController
{
    public string host(Request request) => request.getHost();   // compiles: the request arrives as a parameter
    public string origin() => _SERVER["HTTP_HOST"];             // parse error
}
```

A module binds `Deploy`, and the standard library's module gives it the app's `Environment` (section 32):

<!-- example environment -->
```csharp
// app/Module.sharp
namespace App;

module
{
    transient Deploy = Deploy;   // every class that takes a Deploy gets one with the app's Environment
}
```
<!-- run environment
public ExampleRun(private Deploy deploy) { }
this.deploy.target();   // throws OutOfRangeException
-->

`Deploy` holds an `Environment`, so it has that effect (see the rule on a class's effects below).

**Plain PHP that reads globals, such as a legacy page, shares them through `PhpGlobals`,** a standard `foreign` class in `Sharp.Interop`, since PHP# has no `global` statement. Each member reads or writes the globals of the running request:

- `Any? get(string name)` reads the PHP global `name`, or gives null when it is not set.
- `void set(string name, Any? value)` writes the PHP global `name`.
- `Any? read(Superglobal which, string key)` reads `key` of a superglobal, or gives null when it is not set. `Superglobal`, in `Sharp.Interop`, has the cases `Server`, `Get`, `Post`, `Files`, `Request`, `Cookie`, `Env` and `Session`.
- `void write(Superglobal which, string key, Any? value)` writes `key` of a superglobal.
- `void include(string path)` runs a plain PHP file in the global scope, as PHP's `include` at the top of a script does, so the globals it reads are the ones `set` wrote, and a global it writes is what `get` reads afterwards. A file that cannot be read throws `RuntimeException`.

<!-- example php-globals -->
```csharp
// app/LegacyPage.sharp
namespace App;

import Sharp.Interop.PhpGlobals;
import Sharp.Interop.Superglobal;

public class LegacyPage
{
    public LegacyPage(private PhpGlobals globals) { }

    public Any? render(string tenant)
    {
        this.globals.set("tenant", tenant);
        this.globals.write(Superglobal.Server, "HTTP_HOST", "shop.example");
        this.globals.include("legacy/header.php");   // the file reads $tenant and $_SERVER['HTTP_HOST']

        return this.globals.get("title");             // a global the file wrote
    }
}
```

`LegacyPage` holds a `PhpGlobals`, so it has that effect.

**A `foreign` object is an ordinary type in a constructor, a method parameter and a return value:**

<!-- example foreign-constructor -->
```csharp
public class TenantCache
{
    public TenantCache(private RedisStore store) { }
}
```

<!-- example foreign-parameter -->
```csharp
// app/Shop/Stamp.sharp
namespace App.Shop;

import Sharp.Time.Clock;

public class Stamp
{
    public string stampedWith(Clock clock) => clock.now().toString();   // compiles: the method has the effect Clock
}
```

- **A class's effects** are the `foreign` classes it holds, directly or through its fields, or that its methods take, the effects of the plain PHP its methods call, and the events its methods emit. A method that calls a `foreign` object it takes has that effect, as when it calls one it holds, and a caller that uses one a method returns has the effect at that call. The checker works them out from field, constructor and parameter types and from method bodies. Nothing is written down.
- **A `foreign` object** is created in a module binding (section 32), as often as its binding's lifetime decides, and passed on through constructors, method parameters and return values. Creating one anywhere else in PHP# code, or storing one in a static, is a compile error. Plain PHP creates one as it creates any object.

**Pure code** reaches no `foreign` object, emits no event, and calls no plain PHP but what an `extern` line declares `uses none`. Pure limits only what code reaches outside the program, so a pure method may change its own object and its arguments. `readonly` after the parameters is what promises a method changes nothing, as in `T peek() readonly;`, and it holds every implementation to that (section 13). A `get` body has whatever effects its body has, and a read of the property carries them (section 6.1). Laws (section 28) reason only about pure code that changes nothing it was given, so a law that reaches a change is a compile error naming the method, and a law cannot see inside `foreign` classes or plain PHP.

**A struct and an enum are pure** (sections 10 and 20). Each method, `get` body and operator of one, a data case's included, has no effects, as a value is the same wherever code reads it. A struct or an enum calls only plain PHP its `extern` line declares `uses none`. A struct method that calls a `foreign` parameter, or a plain PHP method whose line names an effect or says `uses any`, and an enum method that emits, is a compile error that names the member and the effect: "`Money.inEuros` has the effect `Http`, and a struct has no effects.", or "`Money.check` may have any effect, and a struct has no effects.". The work goes in a class that takes the value. Creating and throwing an exception is no effect, so a struct may validate its fields with a plain PHP validator declared `uses none`, and throw a `ValueError`:

<!-- example struct-plain-php -->
```php
namespace Vendor\Rules;

final class Validator
{
    public static function check(string $value): bool { return $value !== ''; }
}
```

<!-- example struct-plain-php -->
```csharp
// app/Stubs/Rules.sharp
namespace App.Stubs;

import Vendor.Rules.Validator;

extern Validator.check uses none;
```

<!-- example struct-plain-php -->
```csharp
// app/Sku.sharp
namespace App;

import Vendor.Rules.Validator;

public struct Sku
{
    public string code { get; init; }

    public bool isValid() => Validator.check(this.code);   // compiles: Validator.check is declared uses none
}
```

**Code without a body is pure unless it says `uses`.** This covers interface methods, abstract methods and function types, except the function type of a method's parameter, which takes on what each call passes (below). A declaration that overrides another is held to it: a sub-interface may narrow its parent's `uses`, and a `uses` that widens it is a compile error at the declaration. Every implementation is held to what the declaration allows, and so is each default of its parameters, which a call of the declaration that leaves the argument out runs:

<!-- example effectful-declarations: members -->
```csharp
public interface PaymentGateway
{
    Money quote(Cart cart);                          // implementations must be pure
    Charge charge(Cart cart) uses Http;              // implementations may reach Http, nothing else
}

Function<Money(Offer)> priceOf = (offer) => Money.zero("USD");      // a pure function value
Function<Charge(Cart) uses Http> charge = (cart) => new Charge();   // may reach Http
```

**A function type lists its effects inside its brackets,** after the parameter types, wherever the type is written:

<!-- example function-type-effects: members -->
```csharp
Function<Charge(Cart) uses Http> charge = (cart) => new Charge();          // compiles
Function<Charge(Cart) uses Http>? fallback = null;                          // compiles
Map<string, Function<Charge(Cart) uses Http, Mail>> handlers = [:];         // compiles: Http and Mail are both effects
Function<int(Function<int(int) uses Http>)> apply = (step) => 0;            // compiles
```

An implementation that calls plain PHP fits the declaration only through an `extern` line that names effects the declaration allows:

<!-- example extern-implementations -->
```csharp
// app/AudienceSync.sharp
namespace App;

public interface AudienceSync
{
    void sync(Contact contact) uses Http;
}
```

<!-- example extern-implementations -->
```csharp
// app/StripeGateway.sharp
namespace App;

import Stripe.StripeClient;

public class StripeGateway : PaymentGateway
{
    public Money quote(Cart cart) { … }
    public Charge charge(Cart cart) => StripeClient.charges().create(…);   // fits: StripeClient is declared Http
}
```

<!-- example extern-implementations -->
```csharp
// app/MailchimpSync.sharp
namespace App;

import Mailchimp.Mailchimp;

public class MailchimpSync : AudienceSync
{
    public void sync(Contact contact) { Mailchimp.lists().addListMember(…); }   // compile error
}
```

```text
`Mailchimp` has no `extern` line.
Declare it in a .sharp file, such as `extern Mailchimp uses any;`, and name its effects in place of `any` when you know them.
```

**`uses Events` lets an implementation emit:**

<!-- example emit-effect -->
```csharp
// app/OrderPaid.sharp
namespace App;

public event OrderPaid(int orderId, int total);
```

<!-- example emit-effect -->
```csharp
// app/Checkout.sharp
namespace App;

public interface Checkout
{
    void complete(Order order) uses Events;                                            // implementations may emit, nothing else
}
```

<!-- example emit-effect -->
```csharp
// app/StoreCheckout.sharp
namespace App;

public class StoreCheckout : Checkout
{
    public void complete(Order order) { emit new OrderPaid(order.id, order.total); }    // fits: emit has the effect Events
}
```

**A method takes on the effects of each function it is given.** A parameter whose function type states no effects, as `f` in `map(Function<TResult(T)> f)`, has at each call the effects of the function that call passes. The method has its body's own effects plus those, and nothing is written, as a call to plain PHP takes on the effects of each lambda passed to it.

**Such a parameter runs only inside the call,** because the call's effects cover it only while the call runs. Storing it in a field, a collection or a new object, returning it, and capturing it in a lambda that escapes are compile errors that name the type to take instead: the type of the field or parameter that receives it when that type states effects, its own type with `uses none` when that type is pure, and otherwise its own type with `uses none` or with the effects it may have. Calling it, and passing it on to another method's function parameter, are allowed. A local may hold it, and the local is held to the same rules.

<!-- example function-effects: statements in Carts<T>
List<Cart> carts = [new Cart()];
private PaymentGateway gateway = new StripeGateway();
-->
```csharp
// in the standard library's List<T>
public List<TResult> map<TResult>(Function<TResult(T)> f);          // map takes on the effects of the f it calls

public Charge charge(Cart cart, Function<Cart(Cart)> prepare)
    => this.gateway.charge(prepare(cart));                            // Http, plus prepare's effects

public Holder keep(Function<string()> body)
    => new Holder(body);                                              // compile error: body can't be stored; take the Function<string() uses Environment> that Holder's constructor takes

carts.map(c => c.total);                                              // pure
carts.map(c => this.gateway.charge(c));                               // has Http
```

```text
`body` can't be stored in a new object: it takes on each caller's effects.
Take a `Function<string() uses Environment>`, whose type states the effects it may have.
```

**A parameter whose function type states its effects keeps them as a cap.** `Function<Charge(Cart) uses Http>` accepts a function with `Http` or no effects, and the method has `Http` from calling it. Such a parameter may be stored, returned and captured, as any function value may.

**`uses none` states that a function type is pure.** It is the empty effect list, and goes wherever a function type's `uses` goes. A parameter of `Function<int(int) uses none>` takes only a pure function, so passing it a function with an effect is a compile error at the call, and the method may store, return and capture it, as one whose type states effects. A function type without `uses` anywhere but a method's parameter is pure already, so `uses none` there says nothing new. `uses none` beside another effect, as in `uses none, Http`, is a compile error. `uses none` on a method, which is pure without `uses`, changes nothing, so it warns and compiles. An override's function parameter takes on each caller's effects wherever the parameter it overrides does, a plain PHP `callable` or `Closure` parameter included, because a call through the overridden method may pass a function with any effect.

<!-- example pure-function-parameters: statements
namespace App.Shop;
import App.Cart;
import App.PaymentGateway;
import App.StripeGateway;
Pricing pricing = new Pricing();
private Rates rates = new Rates();
public class Rates
{
    private PaymentGateway gateway = new StripeGateway();

    public int convert(int cents)
    {
        this.gateway.charge(new Cart());
        return cents;
    }
}
-->
```csharp
public class Pricing
{
    private Function<int(int)> rate = (cents) => cents;          // a field: no uses means pure, as today

    public void setRate(Function<int(int) uses none> rate)
        => this.rate = rate;                                      // compiles: the type states it's pure

    public void apply(Function<int(int)> step)
        => this.rate = step;                                      // compile error: step takes on its caller's effects, so it can't be stored; take a Function<int(int) uses none>
}

pricing.setRate((cents) => cents * 2);                            // compiles: a pure lambda
pricing.setRate((cents) => this.rates.convert(cents));            // compile error: setRate takes a pure function, and this one has Http
```

The standard library's collection methods (section 12) take on the effects of their functions this way, so list code with pure functions stays pure, and laws (section 28) can reason about it.

**Plain PHP can run any function it is given,** as Laravel's `Collection.map(f)` and `Foundation.app().call(f)` do. So a call or a `new` of plain PHP has the effects its `extern` line lists, plus the effects of each lambda, method value and function value passed to it. A getter that passes `() => this.clock.now()` to a plain PHP method has the effect `Clock`, and `orders.map(o => this.db.save(o))` has the effect `Database` after `extern Collection uses none;`, so it fits `uses Database` and no `uses none`.

**Code with a body writes no `uses`.** Its effects enter through the constructor, through the plain PHP it calls, through the events it emits and through each function it is given, and its body and declaration show all four. The checker works out each method's effects, and its errors name them, as in "`Gauge.level` calls `Clock.now`, which has the effect `Clock`". A `uses` that names a function parameter, with or without a body, is a parse error that says to delete it.

## 30. Tuples

A tuple groups a fixed number of typed values without declaring a class. It is written in square brackets, as a list literal is. Each element is declared type first, as everywhere in PHP#.

<!-- example tuples: statements in Order
public class Date { }
private Money total = new Money(500, "USD");
private int count = 2;
private Date since = new Date();
Order order = new Order();
-->
```csharp
public [Money total, int count, Date since] summarize() { return [this.total, this.count, this.since]; }

const [total, count, since] = order.summarize();     // unpack
const summary = order.summarize();
summary.total;                                        // read by name

public [Money, int] totals() { return [this.total, this.count]; }   // compiles: element names are optional
const [money, items] = order.totals();                              // unpack an unnamed tuple
```

- **A tuple can have any number of elements.** The checker suggests a struct past four.
- **Names are optional in a tuple type.** `[Money, int]` compiles.
- **A named element that holds a function is called by its name,** as a property that holds one is (section 14): `entry.twice(2)` on a `[Function<int(int)> twice, string name]` runs the element's function, checked by its function type.
- **A list literal becomes a tuple** when the declared type is a tuple, whatever its elements are: an element a lambda gives, as `[data, media.values().map((Media r) => Reference.copy(r))]`, and a literal a lambda returns through a generic method into a declared tuple. A tuple a `Map` holds stays one through `get(k) ?? throw`, so `const [tag, named, value] = tags.get(k) ?? throw …` unpacks it.
- **Map entries are tuples:** `for (const [key, plan] of plans)`.

## 31. Expected failures as values

An expected failure, such as a declined card, is returned as a value. An exception means a bug, and it fails loudly.

<!-- example result-enum: members -->
```csharp
public enum Result<T, TError : Error>
{
    case Ok(T value);
    case Err(TError error);                       // Err, so the case never shares a name with the Error interface
}

public Result<Order, PaymentError> charge(Cart cart) => Result.Err(PaymentError.Declined("card expired"));
```

`Result` is an ordinary enum with data from the standard library, so `match`, `is` and the exhaustive-case check work on it unchanged.

**A case of `Result` is the type `Result<Order, PaymentError>.Ok`.** `Result.Ok(…)` and `Result.Err(…)` take their type arguments from their arguments and from the declared type their result goes into, as `charge` above does. A type argument that neither fixes is written on the enum, as in `Result<int, PaymentError>.Ok(5)`. A pattern names the bare case, as in `Result.Ok(Order order)`, and takes the type arguments of the value it tests.

**`try` passes a failure up to the caller.** If the call returns `Err`, the enclosing method returns an `Err` holding the same error immediately. Otherwise `try` gives the `Ok` value.

- **The returned `Err` takes the type arguments of the enclosing method's `Result`,** and it is `==` to the `Err` it holds the error of. An operand whose error type doesn't fit the method's error type is a compile error that names both.
- **`try` is a prefix operator with `!`'s precedence.** A member read and a call bind tighter, so `try this.gateway.charge(cart)` passes up the call's failure, and parentheses read a member of the `Ok` value.
- **The operand is any `Result` expression,** and `try` stands anywhere an expression can, a lambda that returns a `Result` included. In a computed property whose type is a `Result`, `try` returns the failure from the property's getter.

<!-- example try-propagation: members
private CheckoutGateway gateway = new CheckoutGateway();
private Cart cart = new Cart();
-->
```csharp
public Result<Receipt, PaymentError> checkout(Cart cart)
{
    const order = try this.gateway.charge(cart);      // Order, or return the failure here
    return Result.Ok(order.receipt());
}

public Result<int, PaymentError> total(Cart cart) => Result.Ok((try this.gateway.charge(cart)).total);   // a member of the Ok value

public Result<int, PaymentError> cartTotal => Result.Ok((try this.gateway.charge(this.cart)).total);   // the getter returns the failure
```

`try`/`catch` blocks remain for exceptions, which are now mostly limited to the edges, such as wrapping a plain PHP exception.

Methods declare no thrown exceptions, and the checker never reports an undeclared one.

**Every failure type implements `Error`,** a standard-library interface. One handler can then render any failure, such as an API turning it into an RFC 9457 response.

```text
// in the standard library's Sharp
public interface Error { string message { get; } }
```

<!-- example error-interface -->
```csharp
// app/PaymentError.sharp
namespace App;

public enum PaymentError : Error
{
    case Declined(string reason);
    case Expired;
    public string message => match (this) {
        PaymentError.Declined(string reason) => reason,
        PaymentError.Expired => "card expired",
    };
}
```

**Inside `.sharp` files, a bare `Error` means this interface by default,** and an import or a class of the file's own namespace named `Error` shadows it, as it shadows every default name (section 23). PHP's global engine class `Error` is never named in PHP# code, so an import of it is a compile error. The standard library alone imports it, to state its members' effects on an `extern` line (section 29).
- **Bugs are caught as `Throwable`,** so `catch (Error failure)` is a compile error that names `Throwable`. Engine subclasses such as `TypeError` keep their names.

## 32. Modules

A module says how the app builds its objects. Each namespace folder may hold `Module.sharp`, a `module { … }` declaration with no name, because the folder names it. `Module` is a reserved file name.

**A module declares services** with `singleton`, `scoped` and `transient`:

<!-- example module-services -->
```csharp
// app/Shop/Module.sharp
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.SmtpMailer;
import App.Mail.QueueMailer;
import App.Pricing.PriceRule;
import App.Pricing.TaxRule;
import App.Pricing.DiscountRule;
import App.Cache.RedisStore;
import Sharp.System.Environment;

module
{
    singleton Mailer = SmtpMailer;                                             // every class that takes a Mailer gets the SmtpMailer
    singleton Mailer for SendReceipt = QueueMailer;                            // SendReceipt gets a QueueMailer instead
    singleton List<PriceRule> = [TaxRule, DiscountRule];                       // a class that takes a List<PriceRule> gets both
    singleton RedisStore(Environment environment) = new RedisStore(environment.require("REDIS_URL"));   // a foreign object, created here as its singleton lifetime decides
    transient SendReceipt = SendReceipt;                                       // a class binds to itself, so PHP# creates it
}
```

- **A binding's value is a class or one expression.** A class, such as `SmtpMailer`, is built through its main constructor (section 9.1). A class binds to itself to be created, as `SendReceipt` does.
- **A binding names the services it needs as parameters, and its value is one expression over them.** The container passes each parameter the service bound to its type, so `RedisStore` above gets the app's `Environment`, which the standard library's module binds for every namespace. An expression is for a value no binding gives, such as `RedisStore`'s URL.
- **A module stays declarative:** one expression per binding, and no statements, loops or methods.
- **A `foreign` object is created in a module binding** (section 29). The binding's lifetime decides how often one is created, and the container passes it to classes through their constructors.
- **A module also states the laws that span its classes** (section 28), **and lists the namespaces its code imports from** (below).
- **A class a module binds reaches code only through injection,** a constructor or a method parameter, or a value PHP# code returns. A plain PHP call, a function or a method of a plain PHP class, whose result is such a class, or whose result PHP# code narrows to one with `as` or `is`, is a compile error: "`StoreService` is bound in `App.Shop`'s module, so it reaches code through a constructor or a parameter." So a service locator, as Laravel's `app(StoreService::class)`, never hands PHP# code a bound class, and every one the code holds is the container's. The one exception is the expression of the binding that binds a plain PHP class, where a framework's object enters the container: `scoped Request = Foundation.request();` compiles, and the same call anywhere else stays an error.
- **A namespace whose `Module.sharp` binds its children's classes is the app's composition root, so code its children share lives in a child namespace, never in the root,** because the root imports every child and no namespace may import it back. The root's module imports each class it binds, and no two namespaces import each other (section 23). So `Flags`, which `App.Mail.FlaggedMailer` takes and `App`'s module binds, is `App.Features.Flags`, never `App.Flags`:

<!-- example composition-root -->
```csharp
// app/Features/Flags.sharp
namespace App.Features;

public class Flags
{
    public bool enabled(string name) => name == "queue";
}
```

<!-- example composition-root -->
```csharp
// app/Mail/FlaggedMailer.sharp
namespace App.Mail;

import App.Features.Flags;          // compiles: App.Mail imports App.Features, never App

public class FlaggedMailer : Mailer
{
    public FlaggedMailer(private Flags flags) { }
}
```

<!-- example composition-root -->
```csharp
// app/Module.sharp
namespace App;

import App.Features.Flags;
import App.Mail.Mailer;
import App.Mail.FlaggedMailer;

module
{
    singleton Flags = Flags;
    singleton Mailer = FlaggedMailer;
}
```

**The host defines each lifetime.** A `singleton` lives as long as the host runs the app. A `scoped` binding lives for one scope, which the host starts and ends, such as one HTTP request, one queued job or one console command run. A `transient` binding is created each time a class needs one.

**A host starts and ends each scope with `Scope.run(body)`.** While `body` runs, each `scoped` service it builds lives until `body` returns or throws, and is then dropped. `Scope.run` returns what `body` returns, and has `body`'s effects (section 29). PHP's own request is the default scope, so the CLI and FrankenPHP outside worker mode need no call. A long-running host wraps each unit of work in it, such as an Octane request, a FrankenPHP worker request or a queued job. Plain PHP calls it as `\Sharp\Scope::run(fn () => …)`.

<!-- example host-scope -->
```csharp
// app/Jobs/Worker.sharp
namespace App.Jobs;

import Sharp.Scope;

public class Worker
{
    public Worker(private JobQueue queue) { }

    public void work()
    {
        for (const job of this.queue.pending())
        {
            Scope.run(() => job.handle());   // each job gets its own scoped services
        }
    }
}
```

**A scope follows the fiber that runs in it.** A fiber started inside `Scope.run` belongs to that scope, and a `Scope.run` inside a fiber is that fiber's own, so code on another fiber never builds from it. A `scoped` service resolved after its scope ended throws `Sharp\ContainerError`.

<!-- example scope-fibers -->
```csharp
// app/Mail/Module.sharp
namespace App.Mail;

module
{
    scoped Mailer = SmtpMailer;   // each scope builds its own; a lookup of App\Mail\Mailer starts at App.Mail
}
```

<!-- example scope-fibers -->
```php
<?php
// A plain PHP event loop, such as Revolt's, runs each request in a fiber of its own.
function serve(\Sharp\Container $container): void
{
    $request = new Fiber(fn () => \Sharp\Scope::run(function () use ($container): void {
        $first = $container->get('App\Mail\Mailer');           // the fiber's scope builds it
        Fiber::suspend();
        $again = $container->get('App\Mail\Mailer');           // the same object: the fiber still runs in its scope
        echo $first === $again ? "one Mailer\n" : "two Mailers\n";
    }));
    $request->start();
    $container->get('App\Mail\Mailer');                         // the request's own scope builds another
    $request->resume();
}
```

A binding's expression runs each time its lifetime needs a new service, so a choice made while the app runs is one expression:

<!-- example scoped-binding
namespace App.Shop;

import App.Flags.FeatureFlags;
import App.Mail.Mailer;
import App.Mail.QueueMailer;
import App.Mail.SmtpMailer;
-->
```csharp
// app/Shop/Module.sharp
module
{
    singleton FeatureFlags = FeatureFlags;
    transient QueueMailer = QueueMailer;
    transient SmtpMailer = SmtpMailer;
    scoped Mailer(FeatureFlags flags, QueueMailer queue, SmtpMailer smtp) = flags.enabled("queue-mail") ? queue : smtp;   // runs once per request, job or command
}
```

<!-- example module-errors: alone
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.SmtpMailer;
import App.Pricing.TaxRule;
-->
```csharp
// app/Shop/Module.sharp
module
{
    let fallback = new SmtpMailer();   // compile error: a module holds no statements; give a binding one expression
    singleton Mailer = TaxRule;        // compile error: TaxRule is not a Mailer
}
```

**A nested folder's module overrides its parent's services.** A test or environment module overrides with `module : App.Shop { … }`, and the compiler checks every environment's set of services: the base modules under all of that environment's modules at once.

- **An environment module is `Module.<Environment>.sharp`** beside the `Module.sharp` it overrides, such as `Module.Production.sharp`. `SHARP_ENV` picks it.
- **A test module is declared in the test file that uses it,** with the same header. While a test of that file runs, its services are the test module's over the base modules, with no environment module, which is the set the compiler checks.

<!-- example environment-module -->
```csharp
// app/Shop/Module.Production.sharp
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.QueueMailer;

module : App.Shop
{
    singleton Mailer = QueueMailer;                                            // in production, replaces App.Shop's SmtpMailer
}
```

<!-- example test-module -->
```csharp
// tests/Shop/CheckoutTest.sharp
namespace App.Shop;

import App.Mail.Mailer;
import Tests.Fakes.FakeMailer;

module : App.Shop
{
    singleton Mailer = FakeMailer;                                             // the tests in this file get a FakeMailer
}

public class CheckoutTest { … }
```

**A missing or mistyped service is a compile error.** PHP# creates the classes a module binds and the classes that declare an `on` method (section 15), each through its main constructor (section 9.1). Every parameter of their constructors, and every binding parameter, needs a binding in the module of its namespace or of a namespace above it. The compiler checks every such class, whether or not another class takes it. A class no module binds is ordinary code, created with `new`, such as `Money`.

<!-- example missing-service -->
```csharp
// app/Shop/Checkout.sharp
namespace App.Shop;

import App.Mail.Mailer;
import App.PaymentGateway;

public class Checkout
{
    public Checkout(private Mailer mailer, private PaymentGateway gateway) { }
}
```

<!-- example missing-service
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.SmtpMailer;
-->
```csharp
// app/Shop/Module.sharp
module
{
    singleton Mailer = SmtpMailer;
    transient Checkout = Checkout;   // compile error: Checkout needs a PaymentGateway, and no module binds one
}
```

**A module lists the namespaces its code imports from** on one `imports` line. An import of any other namespace is a compile error at the import, which names the namespace and its list.

<!-- example module-imports -->
```csharp
// app/Tenant/Store/Module.sharp
namespace App.Tenant.Store;

module
{
    imports App.Shop.Loyalty, App.Shop.Records, Illuminate.Support;
}
```

<!-- example module-imports -->
```csharp
// app/Tenant/Store/StoreService.sharp
namespace App.Tenant.Store;

import App.Shop.Loyalty.Member;         // compiles
import Illuminate.Support.Facades.DB;   // compiles: Illuminate.Support lists every namespace below it
import App.Shared.Ledger.Entry;         // compile error: App.Tenant.Store imports only App.Shop.Loyalty, App.Shop.Records and Illuminate.Support

public class StoreService { … }
```

- **The line is optional.** A namespace with no `imports` line imports from any namespace.
- **A list covers its namespace and every namespace below it.** A namespace below may list its own imports, and an import must then pass every list above it. So an entry a list above leaves out allows nothing, and is a compile error on that entry: "`{name}` is outside `{parent}`'s list, so this entry allows nothing."
- **Listing a namespace lists every namespace below it.**
- **A namespace always imports from itself, and from every namespace below the namespace that lists,** so under `App.Tenant.Store`'s list, `App.Tenant.Store.Checkout` imports from `App.Tenant.Store.Receipts`.
- **`Sharp`, the standard library, is always imported and never listed.** A plain PHP library is listed by its namespace, as `Illuminate.Support` is.
- **A name written in full in code counts as an import,** so `App.Shared.Ledger.Entry.record()` is refused as `import App.Shared.Ledger.Entry;` is.
- **A list allows imports and never makes a cycle.** Whatever the lists say, no two namespaces import each other in the imports the code writes (section 23).
- **A listed name that names no namespace is a compile error,** as an import of an unknown name is.

<!-- example module-imports-errors -->
```csharp
// app/Shop/Records/Module.sharp
namespace App.Shop.Records;

module
{
    imports App.Tenant.Store, App.Shared.Ledgr;        // compile error: App.Shared.Ledgr names no namespace
}
```

**The container implements PSR-11,** so any framework's container can ask PHP#'s container for a PHP# class. The language never depends on a framework. `get(id)` and `has(id)` look for a binding in the module of the id's own namespace first, then in each namespace above it, as a constructor parameter's lookup starts at its class's namespace. So `get('App\Mail\Mailer')` reads `App.Mail`'s module, then `App`'s, and never `App.Shop`'s, and `has('App\Mail\Mailer')` is false when only `App.Shop`'s module binds `Mailer`.

## Undecided, in order

None. The standard library is specified with the library itself.
