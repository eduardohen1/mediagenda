package br.com.mediagenda.controllers;

import br.com.mediagenda.model.Medico;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.ArrayList;

@RestController
@RequestMapping("/api/medicos") //http://localhost:8080/api/medicos
@CrossOrigin("*")
public class MedicoController {

    @GetMapping("/listar_todos") //http://localhost:8080/api/medicos/listar_todos
    public List<Medico> listarTodos(){
        Medico medico = new Medico();
        List<Medico> medicos = new ArrayList<>();
        
        medico.setId(1L);
        medico.setNome("Teste");
        medico.setCrm("MG123456");
        medico.setEspecialidadeId(1L);
        medicos.add(medico);

        medico = new Medico();
        medico.setId(2L);
        medico.setNome("Teste2");
        medico.setCrm("MG123457");
        medico.setEspecialidadeId(2L);
        medicos.add(medico);

        medico = new Medico();
        medico.setId(3L);
        medico.setNome("Teste3");
        medico.setCrm("MG123447");
        medico.setEspecialidadeId(3L);
        medicos.add(medico);

        return medicos;
    }

    @GetMapping //GET http://localhost:8080/api/medicos ==> Olá mundo!
    public String hello(){
        return "Olá mundo!";
    }

}