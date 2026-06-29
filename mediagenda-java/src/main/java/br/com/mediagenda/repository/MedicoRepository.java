package br.com.mediagenda.repository;

import br.com.mediagenda.model.Medico;
import org.springframework.data.jpa.repository.JpaRepository;

public interface MedicoRepository extends JpaRepository<Medico, Long>{
    
}