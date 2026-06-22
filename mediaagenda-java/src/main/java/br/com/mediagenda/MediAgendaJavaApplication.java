package br.com.mediagenda;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

@SpringBootApplication
public class MediAgendaJavaApplication{
    public static void main(String[] args){
        SpringApplication.run(MediAgendaJavaApplication.class,args);    
        System.out.println("Rodando Java!");
    }
}