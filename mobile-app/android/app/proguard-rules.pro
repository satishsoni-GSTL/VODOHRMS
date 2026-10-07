# Release (R8) keep rules.

# flutter_local_notifications stores scheduled notifications with Gson; keep its generic
# type information or R8 strips it ("Missing type parameter" crash at runtime).
-keep class com.dexterous.** { *; }
-keepattributes Signature
-keepattributes *Annotation*
-keep class * extends com.google.gson.TypeAdapter
-keep class * implements com.google.gson.TypeAdapterFactory
-keep class * implements com.google.gson.JsonSerializer
-keep class * implements com.google.gson.JsonDeserializer
-keep class com.google.gson.reflect.TypeToken { *; }
-keep class * extends com.google.gson.reflect.TypeToken

# Firebase Messaging
-keep class com.google.firebase.messaging.** { *; }

# Flutter deferred components reference Play Core classes that aren't bundled.
-dontwarn com.google.android.play.core.**
