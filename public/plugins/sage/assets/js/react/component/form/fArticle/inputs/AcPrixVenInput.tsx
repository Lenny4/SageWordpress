import * as React from "react";
import { useImperativeHandle, useRef } from "react";
import { IconButton, Tooltip } from "@mui/material";
import InfoIcon from "@mui/icons-material/Info";
import { getTranslations } from "../../../../../functions/translations";
import {
  FormValidInterface,
  InputInterface,
} from "../../../../../interface/InputInterface";
import { handleChangeInputGeneric } from "../../../../../functions/form";
import { TOKEN } from "../../../../../token";
import { numberValidator } from "../../../../../functions/validator";

let translations: any = getTranslations();

export type AcPrixVenInputState = {
  defaultValue: number;
  acCategorie: number | string;
  acCoef: number | string;
  arPrixAch: number | string;
};

type FormState = {
  acPrixVen: InputInterface;
  realAcPrixVen: InputInterface;
  valueLock: InputInterface;
};

export const AcPrixVenInput = React.forwardRef(
  (
    { defaultValue, acCategorie, acCoef, arPrixAch }: AcPrixVenInputState,
    ref,
  ) => {
    const inputRef = useRef<any>(null);
    acCoef = Number(acCoef);
    arPrixAch = Number(arPrixAch);
    const getExpectedAcPrixVen = () => {
      return Number((acCoef * arPrixAch).toFixed(2));
    };
    const [expectedAcPrixVen, setExpectedAcPrixVen] = React.useState(
      getExpectedAcPrixVen(),
    );

    const getDefaultValue = (): FormState => {
      const v = defaultValue ?? 0;
      return {
        acPrixVen: { value: v.toString() },
        realAcPrixVen: { value: v.toString() },
        valueLock: { value: v > 0 },
      };
    };
    const [values, setValues] = React.useState<FormState>(getDefaultValue());

    const handleChange =
      (prop: keyof FormState) =>
      (event: React.ChangeEvent<HTMLInputElement>) => {
        setValues((v) => {
          return {
            ...v,
            valueLock: {
              ...v.valueLock,
              value: true,
            },
          };
        });
        handleChangeInputGeneric(event, prop, setValues);
      };
    const handleRealAcPrixVen = () => {
      setValues((v) => {
        return {
          ...v,
          realAcPrixVen: {
            ...v.realAcPrixVen,
            value:
              v.valueLock.value &&
              expectedAcPrixVen !== Number(v.acPrixVen.value)
                ? v.acPrixVen.value
                : "0",
          },
        };
      });
    };

    const resetAcPrixVen = () => {
      const newValue = getExpectedAcPrixVen();
      setValues((v) => {
        return {
          ...v,
          acPrixVen: {
            ...v.acPrixVen,
            value: newValue.toString(),
          },
          valueLock: {
            ...v.valueLock,
            value: false,
          },
        };
      });
      setExpectedAcPrixVen(newValue);
    };

    const isValid = async () => {
      const error = await numberValidator({
        value: values.acPrixVen.value,
      });
      setValues((v) => {
        return {
          ...v,
          acPrixVen: {
            ...v.acPrixVen,
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

    React.useEffect(() => {
      isValid();
    }, [values.acPrixVen.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      handleRealAcPrixVen();
    }, [expectedAcPrixVen, values.acPrixVen.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      resetAcPrixVen();
    }, [acCoef, arPrixAch]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
      <>
        <label htmlFor={`_${TOKEN}_acPrixVen`}>
          <Tooltip title={"acPrixVen"} arrow placement="top">
            <span>{translations["fArticles"]["acPrixVen"]}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex", alignItems: "flex-start" }}>
          <div style={{ position: "relative", flex: 1 }}>
            <input
              id={`_${TOKEN}_fArtclients[` + acCategorie + "].acPrixVen"}
              name={`_${TOKEN}_fArtclients[` + acCategorie + "].acPrixVen"}
              type={"hidden"}
              value={values.realAcPrixVen.value}
            />
            <input
              type={"number"}
              value={values.acPrixVen.value}
              onChange={handleChange("acPrixVen")}
              style={{ width: "100%" }}
              ref={inputRef}
              onBlur={() => {
                if (Number(values.acPrixVen.value) === 0) {
                  resetAcPrixVen();
                }
              }}
            />
            {values.acPrixVen.error && (
              <div className={`${TOKEN}_error_field`}>
                {values.acPrixVen.error}
              </div>
            )}
          </div>
          {Number(values.acPrixVen.value) !== expectedAcPrixVen &&
            Number(values.acPrixVen.value) > 0 && (
              <div style={{ position: "relative", top: "-2px" }}>
                <Tooltip title={translations.sentences.acPrixVenInput} arrow>
                  <IconButton>
                    <InfoIcon fontSize="small" />
                  </IconButton>
                </Tooltip>
              </div>
            )}
        </div>
      </>
    );
  },
);
