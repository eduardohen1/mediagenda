package br.com.mediagenda.controllers;

import br.com.mediagenda.model.Medico;
import br.com.mediagenda.service.MedicoService;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.ArrayList;

@RestController
@RequestMapping("/api/medicos") //http://localhost:8080/api/medicos
@CrossOrigin("*")
public class MedicoController {

    //trazer o serviço para a controladora
    private final MedicoService medicoService;

    //construtor
    public MedicoController(MedicoService medicoService){
        this.medicoService = medicoService;
    }

    @GetMapping("/listar_todos") //http://localhost:8080/api/medicos/listar_todos
    public List<Medico> listarTodos(){
        /*Medico medico = new Medico();
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
        return medicos;*/
        return medicoService.listarTodos();
    }

    @GetMapping //GET http://localhost:8080/api/medicos ==> Olá mundo!
    public String hello(){
        return "Olá mundo!";
    }

    @GetMapping("/{id}") //GET http://localhost:8080/api/medicos/1 == 1 é o ID do parâmetro
    public ResponseEntity<Medico> buscarPorId(@PathVariable Long id){
        return medicoService.buscarPorId(id)
                .map(medico -> ResponseEntity.ok(medico))
                .orElse(ResponseEntity.notFound().build());
    }

    @PostMapping //POST
    public ResponseEntity<Medico> gravar(@RequestBody Medico medico){
        return ResponseEntity.ok(medicoService.gravar(medico));
    }

    @PutMapping("/{id}") //PUT http://localhost:8080/api/medicos/1
    public ResponseEntity<Medico> atualizar(
        @PathVariable Long id,
        @RequestBody Medico medico1
    ){
        return ResponseEntity.ok(medicoService.atualizar(id, medico1));
        
    }

    @DeleteMapping("/{id}") //DELETE http://localhost:8080/api/medicos/1
    public ResponseEntity<Void> deletar(@PathVariable Long id){
        medicoService.deletar(id);
        return ResponseEntity.noContent().build();
    }

}