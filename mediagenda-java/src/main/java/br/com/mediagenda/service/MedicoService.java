package br.com.mediagenda.service;

import br.com.mediagenda.model.Medico;
import br.com.mediagenda.repository.MedicoRepository;
import org.springframework.stereotype.Service;

import java.util.List;
import java.util.Optional;

@Service
public class MedicoService{

    //argumentos
    private final MedicoRepository medicoRepository;

    //método construtor
    public MedicoService(MedicoRepository medicoRepository){
        this.medicoRepository = medicoRepository;
    }

    //métodos CRUD
    public List<Medico> listarTodos(){
        //select * from medicos
        return medicoRepository.findAll();
    }

    //buscar por ID:
    public Optional<Medico> buscarPorId(Long id){
        return medicoRepository.findById(id); //select * from medico where id = ?
    }

    public Medico gravar(Medico medico){
        return medicoRepository.save(medico); //insert into medico
    }

    public Medico atualizar(Long id, Medico medicoAtualizar){
        Optional<Medico> medicoExistente = medicoRepository.findById(id);
        if(!medicoExistente.isPresent())
            return null;
        
        Medico medicoSalvar = medicoExistente.get();
        medicoSalvar.setNome(medicoAtualizar.getNome());
        medicoSalvar.setCrm(medicoAtualizar.getCrm());
        medicoSalvar.setEspecialidadeId(medicoAtualizar.getEspecialidadeId());
        medicoSalvar.setEmail(medicoAtualizar.getEmail());
        return medicoRepository.save(medicoSalvar);
    }

    public void deletar(Long id){
        medicoRepository.deleteById(id);
    }


}