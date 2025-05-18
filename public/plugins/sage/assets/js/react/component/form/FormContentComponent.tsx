import * as React from "react";
import { FormContentInterface } from "../../../interface/InputInterface";
import { Grid } from "@mui/material";
import { getTranslations } from "../../../functions/translations";

let translations: any = getTranslations();

const defaultContainer = Grid;
const defaultProps = {
  size: { xs: 12, md: 6 },
};

type State = {
  content: FormContentInterface[];
  values: any;
  transPrefix?: string;
  handleChange: any;
};

export const FormContentComponent: React.FC<State> = ({
  content,
  values,
  transPrefix,
  handleChange,
}) => (
  <>
    {content.map(
      ({ Container, props, Dom, fields, children }, indexContainer) => {
        Container = Container ?? defaultContainer;
        props = props ?? defaultProps;

        return (
          <Container {...props} key={indexContainer}>
            {Dom}
            {fields?.map(({ name, DomField, readOnly }, indexField) => {
              let label = "";
              if (
                transPrefix &&
                translations[transPrefix].hasOwnProperty(name)
              ) {
                if (translations[transPrefix][name].hasOwnProperty("label")) {
                  label = translations[transPrefix][name].label;
                } else {
                  label = translations[transPrefix][name];
                }
              } else {
                label = translations.words[name] ?? name;
              }
              return (
                <DomField
                  key={indexField}
                  label={label}
                  name={`_sage_${name}`}
                  value={values[name].value}
                  readOnly={readOnly ?? false}
                  onChange={handleChange(name)}
                />
              );
            })}
            {children && children.length > 0 && (
              <FormContentComponent
                content={children}
                values={values}
                handleChange={handleChange}
                transPrefix={transPrefix}
              />
            )}
          </Container>
        );
      },
    )}
  </>
);
