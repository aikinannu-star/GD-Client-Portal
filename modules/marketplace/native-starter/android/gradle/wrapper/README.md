This folder should contain the Gradle wrapper JAR used by `gradlew`.

If `gradle/wrapper/gradle-wrapper.jar` is missing, run the helper:

PowerShell:

    .\install-gradle-wrapper.ps1 -GradleVersion 7.5.1

Alternatively, on a machine with Gradle installed run from the `android` directory:

    gradle wrapper --gradle-version 7.5.1

Then copy the generated `gradle/wrapper/gradle-wrapper.jar` into this folder.
