Gradle wrapper notes

This starter includes `gradle-wrapper.properties` and `gradlew`/`gradlew.bat` scripts. The `gradle-wrapper.jar` is not included by default.

You have three options to bootstrap the wrapper JAR:

1) Let Android Studio generate the wrapper automatically when you open the project (recommended).

2) Generate locally with a system Gradle installation:

```bash
# from native-starter/android
gradle wrapper --gradle-version 7.5.1
```

3) Use the included helper scripts to download and extract `gradle-wrapper.jar` from the Gradle distribution:

Unix/macOS:
```bash
cd modules/marketplace/native-starter/android
bash fetch-gradle-wrapper.sh 7.5.1
```

Windows PowerShell:
```powershell
cd modules/marketplace/native-starter/android
.\fetch-gradle-wrapper.ps1 -GradleVersion 7.5.1
```

After the `gradle-wrapper.jar` is present, run the wrapper:

```bash
./gradlew assembleDebug  # Unix/macOS
.\gradlew.bat assembleDebug  # Windows
```

If you'd rather I add the `gradle-wrapper.jar` directly into the starter repo, tell me and I'll embed it for you.
