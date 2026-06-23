# Configuração de variáveis de ambiente:
```PowerShell
$env:JAVA_HOME="C:\Program Files\Java\jdk1.8.0_333"
$env:Path="$env:JAVA_HOME\bin;$env:Path"
```

## Testar:
`
java -version
javac -version
`

## Rodando aplicação java
`
.\apache-maven-3.9.16-bin\bin\mvn clean install -X
.\apache-maven-3.9.16-bin\bin\mvn spring-boot:run -X
`