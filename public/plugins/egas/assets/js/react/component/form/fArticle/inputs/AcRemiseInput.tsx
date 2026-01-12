import * as React from "react";
import { useImperativeHandle, useRef } from "react";
import { IconButton, Tooltip } from "@mui/material";
import { getTranslations } from "../../../../../functions/translations";
import {
  FormValidInterface,
  InputInterface,
} from "../../../../../interface/InputInterface";
import { handleChangeInputGeneric } from "../../../../../functions/form";
import { TOKEN } from "../../../../../token";
import { numberValidator } from "../../../../../functions/validator";
import InfoIcon from "@mui/icons-material/Info";

let translations: any = getTranslations();

export type AcRemiseInputState = {
  defaultValue: number;
  acCategorie: number | string;
  acTypeRem: boolean;
  acQteMont: number;
};

type FormState = {
  acRemise: InputInterface;
};

export const AcRemiseInput = React.forwardRef(
  (
    { defaultValue, acCategorie, acTypeRem, acQteMont }: AcRemiseInputState,
    ref,
  ) => {
    const inputRef = useRef<any>(null);

    const getDefaultValue = (): FormState => {
      return {
        acRemise: { value: defaultValue },
      };
    };
    const [values, setValues] = React.useState<FormState>(getDefaultValue());

    const handleChange =
      (prop: keyof FormState) =>
      (event: React.ChangeEvent<HTMLInputElement>) => {
        handleChangeInputGeneric(event, prop, setValues);
      };

    const isValid = async () => {
      const error = await numberValidator({
        value: values.acRemise.value,
        canBeEmpty: true,
      });
      setValues((v) => {
        return {
          ...v,
          acRemise: {
            ...v.acRemise,
            error: error,
          },
        };
      });
      return error === "";
    };

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<FormValidInterface> {
        const valid = await isValid();
        return {
          valid: valid,
          details: [
            {
              valid: valid,
              ref: ref,
              dRef: inputRef,
            },
          ],
        };
      },
    }));

    return (
      <>
        <label htmlFor={`_${TOKEN}_fArtclients[${acCategorie}][acRemise]`}>
          <Tooltip title={"acRemise"} arrow placement="top">
            <span>{translations.fArticles.acRemise}</span>
          </Tooltip>
        </label>
        {!acTypeRem && Number(acQteMont) === 0 ? (
          <div style={{ display: "flex", alignItems: "flex-start" }}>
            <div style={{ position: "relative", flex: 1 }}>
              <input
                type={"number"}
                id={`_${TOKEN}_fArtclients[${acCategorie}][acRemise]`}
                name={`_${TOKEN}_fArtclients[${acCategorie}][acRemise]`}
                value={values.acRemise.value}
                onChange={handleChange("acRemise")}
                style={{ width: "100%" }}
                ref={inputRef}
              />
              {values.acRemise.error && (
                <div className={`${TOKEN}_error_field`}>
                  {values.acRemise.error}
                </div>
              )}
            </div>
          </div>
        ) : (
          <>
            {acTypeRem
              ? translations.fArtclients.acTypeRem
              : translations.fArtclients.acQteMont.values[acQteMont]}
            <Tooltip title={translations.sentences.acRemiseInput} arrow>
              <IconButton>
                <InfoIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          </>
        )}
      </>
    );
  },
);
